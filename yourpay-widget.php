<?php
/*
Plugin Name: Yourpay Payment Platform
Description: Yourpay Widget for manual payments
Text Domain: yourpay.io
Version: 3.0.61
Author: Yourpay
Author URI: http://www.yourpay.io/
*/

class yourpay_widget_plugin extends WP_Widget {

	// constructor
	function yourpay_widget_plugin() {
            parent::WP_Widget(false, $name = __('Yourpay Widget', 'yourpay_widget_plugin') );
	}

        function form($instance) {

        // Check values
        if( $instance) {
             $title = esc_attr($instance['title']);
             $merchantid = esc_attr($instance['merchantid']);
             $accepturl = esc_attr($instance['accepturl']);
             $orderid = esc_attr($instance['orderid']);
             $buttontxt = esc_attr($instance['buttontxt']);
        } else {
             $title = '';
             $merchantid = '';
             $accepturl = '';
             $orderid = 'Ordre ID';
             $buttontxt = 'Klik her for at åbne betalingsvinduet';
        }
        ?>

        <p>
        <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'wp_widget_plugin'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>

        <p>
        <label for="<?php echo $this->get_field_id('merchantid'); ?>"><?php _e('Yourpay MerchantID', 'wp_widget_plugin'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('merchantid'); ?>" name="<?php echo $this->get_field_name('merchantid'); ?>" type="text" value="<?php echo $merchantid; ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('orderid'); ?>"><?php _e('OrdreID Specifikation', 'wp_widget_plugin'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('orderid'); ?>" name="<?php echo $this->get_field_name('orderid'); ?>" type="text" value="<?php echo $orderid; ?>" />
        </p>
        <p>
        <label for="<?php echo $this->get_field_id('accepturl'); ?>"><?php _e('Accept URL', 'wp_widget_plugin'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('accepturl'); ?>" name="<?php echo $this->get_field_name('accepturl'); ?>" type="text" value="<?php echo $accepturl; ?>" />
        Angiv URL hvor brugeren skal returneres til når betalingen er godkendt.
        </p>
        
        <p>
        <label for="<?php echo $this->get_field_id('buttontxt'); ?>"><?php _e('Knap tekst', 'wp_widget_plugin'); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id('buttontxt'); ?>" name="<?php echo $this->get_field_name('buttontxt'); ?>" type="text" value="<?php echo $buttontxt; ?>" />
        </p>
        
        <?php
        }
        function update($new_instance, $old_instance) {
              $instance = $old_instance;
              $instance['title'] = strip_tags($new_instance['title']);
              $instance['merchantid'] = strip_tags($new_instance['merchantid']);
              $instance['accepturl'] = strip_tags($new_instance['accepturl']);
              $instance['orderid'] = strip_tags($new_instance['orderid']);
              $instance['buttontxt'] = strip_tags($new_instance['buttontxt']);
             return $instance;
        }

        // display widget
        function widget($args, $instance) {
           extract( $args );
           // these are the widget options
           $title       = apply_filters('widget_title', $instance['title']);
           $merchantid  = $instance['merchantid'];
           $orderid     = $instance['orderid'];
           $buttontxt   = $instance['buttontxt'];
           
           echo $before_widget;
           // Display the widget
           echo '<div class="widget-text wp_widget_plugin_box">';

           // Check if title is set
           if ( $title ) {
              echo $before_title . $title . "ABC" . $after_title;
           }
           
           // Check if text is set
           if( $merchantid ) {
               
                echo '<p class="wp_widget_plugin_text"><form action="https://payments.yourpay.se/betalingsvindue.php" method="post" class="yourpay">'
                . '<input name="MerchantNumber" type="HIDDEN" value="'.$merchantid.'">'
                . '<input name="ShopPlatform" type="HIDDEN" value="YourpayWidget">';
                if(isset($accepturl)) {
                    echo '<input name="accepturl" type="HIDDEN" value="'.$accepturl.'">';                    
                } else {
                    echo '<input name="accepturl" type="HIDDEN" value="http://payments.yourpay.se/webpay_processed.php?lang=da-dk">';
                }
                echo '<input name="time" type="HIDDEN" value="'.time().'">'
                . '<p class="yourpay_amount_placeholder">Angiv fuldt beløb</p>'
                . '<input class="yourpay_amount_field" name="amount" type="text" placeholder="Angiv fuldt beløb" value="0,00"></p>'
                . '<p class="yourpay_orderid_placeholder">'.$orderid.'</p>'
                . '<input class="yourpay_orderid_field" name="orderid" type="text" placeholder="Angiv '.$orderid.'" value=""></p>'
                . '<input name="currency" type="HIDDEN" value="208">'
                . '<input name="lang" type="HIDDEN" value="da-dk">'
                . '<center>'
                . '<input type="submit" value="'.$buttontxt.'" class="yourpay_submit_button"></center>'
                . '</form>';               
              echo '</p>';
           }

           echo '</div>';
           echo $after_widget;
        }
}
// register widget
add_action('widgets_init', create_function('', 'return register_widget("yourpay_widget_plugin");'));

?>