# Pago Fácil SpA

## Use
### Requirements

- Woocomerce
- Have an associated account in [Pago Fácil](https://dashboard.pagofacil.cl/)

### Installation

- Download the plugin from https://github.com/PSTPAGOFACIL/woocommerce 
- Go to plugins section and upload the .zip file

**NOTE:** If you are in localhost, you have to add this line to wp-config.php file before upload the plugin:

```
define('FS_METHOD', 'direct');

//This line is added after:
// define('WP_DEBUG', false);

```

### Configuration

- For configure go to Woocommerce > Settings > Payments > Pago Fácil
- Set the environment in wich you want to work
- Add the Token Service and Token Secret that provides Pago Fácil

Once these steps are completed, you can use the payment method with Pago Fácil.