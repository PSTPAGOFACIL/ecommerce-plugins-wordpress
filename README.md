# Pago Fácil SpA

## Uso
### Requerimientos

- Instalar Woocommerce .
- Crear una cuenta en [Pago Fácil] (https://dashboard.pagofacil.cl/).

### Instalación

- Descargar el plugin autocontenido desde https://cl.wordpress.org/plugins/webpayplus-pst/.

**NOTA:** En el caso de estar trabajando como localhost , agregar la siguiente linea en wp-config.php:

```
define('FS_METHOD', 'direct');

//Agregar esta linea después de:
// define('WP_DEBUG', false);

```
- Instalar el plugin en Wordpress y activarlo.

### Configuración

- Para configurar Woocommerce , ir a Ajustes (de Woocommerce) > Pagos > Pago Fácil > Gestionar.
- Elegir el ambiente a trabajar (desarrollo o producción).
- Agregar el Service Token y el Secret Token, que se obtienen en el dashboard de Pago Fácil (correspondiente al ambiente seleccionado)

Una vez completados estos pasos , usted puede usar Pago Fácil para recibir pagos.