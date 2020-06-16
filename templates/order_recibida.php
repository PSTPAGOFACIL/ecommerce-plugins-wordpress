<?php
$fields = array(
    "Orden de compra Mall" => get_post_meta($order_id, "_gateway_reference", true),
    "Moneda" => get_post_meta($order_id, "_currency", true),
    "Monto" => get_post_meta($order_id, "_amount", true),
);

?>
<h2><?php echo "Detalles de la Transacción"; ?></h2>
<table class="shop_table order_details">
    <thead>
        <tr>
            <th class="product-name"><?php echo 'Atributo'; ?></th>
            <th class="product-total"><?php echo 'Valor'; ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($fields as $field => $key) {
            echo "<tr>";
            echo "<td>$field</td>";
            echo "<td>$key</td>";
            echo "</tr>";
        }
        ?>

    </tbody>
    <tfoot>

    </tfoot>
</table>
