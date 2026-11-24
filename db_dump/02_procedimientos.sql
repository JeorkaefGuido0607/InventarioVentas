SET NAMES utf8mb4;
USE inventario_ventas;

DELIMITER //

-- Registra un movimiento manual de inventario y actualiza el stock.
CREATE PROCEDURE sp_registrar_movimiento(
    IN p_producto_id INT,
    IN p_usuario_id INT,
    IN p_tipo VARCHAR(20),
    IN p_cantidad INT,
    IN p_motivo VARCHAR(255),
    OUT p_movimiento_id INT,
    OUT p_resultado VARCHAR(150)
)
proc: BEGIN
    DECLARE v_stock INT;
    DECLARE v_nuevo INT;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_movimiento_id = NULL;
        SET p_resultado = 'Error: No fue posible registrar el movimiento.';
    END;

    SET p_movimiento_id = NULL;

    IF p_tipo NOT IN ('Entrada', 'Salida', 'Ajuste') THEN
        SET p_resultado = 'Error: Tipo de movimiento inválido.';
        LEAVE proc;
    END IF;

    IF p_cantidad IS NULL OR p_cantidad < 0 OR (p_tipo <> 'Ajuste' AND p_cantidad = 0) THEN
        SET p_resultado = 'Error: La cantidad indicada no es válida.';
        LEAVE proc;
    END IF;

    START TRANSACTION;

    -- Bloquea la fila del producto para evitar condiciones de carrera
    SELECT stock INTO v_stock
    FROM productos
    WHERE id = p_producto_id
    FOR UPDATE;

    IF v_stock IS NULL THEN
        ROLLBACK;
        SET p_resultado = 'Error: El producto no existe.';
        LEAVE proc;
    END IF;

    IF p_tipo = 'Entrada' THEN
        SET v_nuevo = v_stock + p_cantidad;
    ELSEIF p_tipo = 'Salida' THEN
        IF p_cantidad > v_stock THEN
            ROLLBACK;
            SET p_resultado = CONCAT('Error: Stock insuficiente. Disponible: ', v_stock, '.');
            LEAVE proc;
        END IF;
        SET v_nuevo = v_stock - p_cantidad;
    ELSE
        SET v_nuevo = p_cantidad;
    END IF;

    UPDATE productos SET stock = v_nuevo WHERE id = p_producto_id;

    INSERT INTO movimientos_inventario
        (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo)
    VALUES
        (p_producto_id, p_usuario_id, p_tipo, p_cantidad, v_stock, v_nuevo, p_motivo);

    SET p_movimiento_id = LAST_INSERT_ID();

    COMMIT;
    SET p_resultado = 'Movimiento registrado exitosamente.';
END //


-- Anula una venta: devuelve el stock de cada producto, deja el rastro
-- en la bitácora y cambia el estado a 'Anulada'. No borra registros.
CREATE PROCEDURE sp_anular_venta(
    IN p_venta_id INT,
    IN p_usuario_id INT,
    OUT p_resultado VARCHAR(150)
)
proc: BEGIN
    DECLARE v_estado VARCHAR(20);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_resultado = 'Error: No fue posible anular la venta.';
    END;

    START TRANSACTION;

    SELECT estado INTO v_estado
    FROM ventas
    WHERE id = p_venta_id
    FOR UPDATE;

    IF v_estado IS NULL THEN
        ROLLBACK;
        SET p_resultado = 'Error: La venta no existe.';
        LEAVE proc;
    END IF;

    IF v_estado = 'Anulada' THEN
        ROLLBACK;
        SET p_resultado = 'Error: La venta ya se encuentra anulada.';
        LEAVE proc;
    END IF;

    -- Bitácora (se registra antes de actualizar para guardar el stock anterior)
    INSERT INTO movimientos_inventario
        (producto_id, usuario_id, tipo, cantidad, stock_anterior, stock_nuevo, motivo, venta_id)
    SELECT d.producto_id, p_usuario_id, 'Anulación', d.cantidad, p.stock, p.stock + d.cantidad,
           CONCAT('Anulación de la venta #', p_venta_id), p_venta_id
    FROM detalle_ventas d
    INNER JOIN productos p ON p.id = d.producto_id
    WHERE d.venta_id = p_venta_id
    FOR UPDATE;

    UPDATE productos p
    INNER JOIN detalle_ventas d ON d.producto_id = p.id
    SET p.stock = p.stock + d.cantidad
    WHERE d.venta_id = p_venta_id;

    UPDATE ventas SET estado = 'Anulada' WHERE id = p_venta_id;

    COMMIT;
    SET p_resultado = 'Venta anulada exitosamente.';
END //


-- Detalle de una venta (líneas con el nombre del producto)
CREATE PROCEDURE sp_obtener_detalle_venta(
    IN p_venta_id INT
)
BEGIN
    SELECT d.id, d.producto_id, p.codigo, p.nombre AS producto,
           d.cantidad, d.precio_unitario, d.subtotal
    FROM detalle_ventas d
    INNER JOIN productos p ON p.id = d.producto_id
    WHERE d.venta_id = p_venta_id
    ORDER BY d.id;
END //

DELIMITER ;
