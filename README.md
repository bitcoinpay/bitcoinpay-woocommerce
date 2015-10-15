# BitcoinPay Woocommerce payment module

BitcoinPay payment module for Woocommerce

### Version
1.0

### Installation guide
1. Extract ZIP file
2. Locate directory bitcoinpay-woocommerce and copy it to your server under wp-content/plugins
3. Go to your Wordpress administration panel and navigate: Plugins > Installed plugins > woocommerce Bitcoinpay Payment Gateway. Bitcoinpay plugin must be activated.
4. In Wordpress Menu go to  Woocommerce > settings > checkout > Payment gateways. Locate BitcoinPay and enable it.
5. Configure BitcoinPay plugin.
6. You are ready to accept Bitcoins


### Configuration 

Some merchants need to customize payment statuses. This can be done in folowing code.
```

 if ($paymentStatus != NULL) {
error_log($paymentStatus);
switch ($paymentStatus) {
case 'confirmed':
$order->update_status('processing', __('BCP Payment processing', 'bcp'));
break;
case 'pending':
$order->update_status('pending', __('BCP Payment pending', 'bcp'));
break;
case 'received':
$order->update_status('pending', __('BCP Payment received but still pending', 'bcp'));
break;
case 'insufficient_amount':
$order->update_status('failed', __('BCP Payment failed. Insufficient amount', 'bcp'));
break;
case 'invalid':
$order->update_status('cancelled', __('BCP Payment failed. Invalid', 'bcp'));
break;
case 'timeout':
$order->update_status('cancelled', __('BCP Payment failed. Timeout', 'bcp'));
break;
case 'refund':
$order->update_status('refunded', __('BCP Payment refunded', 'bcp'));
break;
case 'paid_after_timeout':
$order->update_status('failed', __('BCP Payment failed. Paid after timeout', 'bcp'));
break;
}
```

To change payment buttons, you can configure code here:
```
$this->icon_path = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/img/01_32p.png';
```
