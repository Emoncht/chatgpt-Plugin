<?php
/**
 * User Data Handler for OpenAI Chatbot
 * 
 * Handles the retrieval of user data and order history
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class OpenAI_Chatbot_User_Data_Handler {
    
    /**
     * Get user orders for a specific user
     * 
     * @param int $user_id The user ID
     * @param int $limit The maximum number of orders to retrieve
     * @return array An array of orders with their details
     */
    public function get_user_orders($user_id, $limit = 10) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return array();
        }
        
        // Get customer's orders
        $args = array(
            'customer_id' => $user_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $orders = wc_get_orders($args);
        $order_data = array();
        
        foreach ($orders as $order) {
            $order_items = array();
            
            // Get order items
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                
                $order_items[] = array(
                    'product_id' => $product ? $product->get_id() : 0,
                    'name' => $product_name,
                    'quantity' => $quantity,
                    'total' => $item->get_total()
                );
            }
            
            // Add order data
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method_title(),
                'shipping_method' => $order->get_shipping_method(),
                'shipping_address' => $this->format_address($order->get_address('shipping')),
                'billing_address' => $this->format_address($order->get_address('billing')),
                'items' => $order_items
            );
        }
        
        return $order_data;
    }
    
    /**
     * Format an address for easy reading
     * 
     * @param array $address The address array
     * @return string The formatted address
     */
    private function format_address($address) {
        $formatted = '';
        
        if (!empty($address['first_name']) || !empty($address['last_name'])) {
            $formatted .= trim($address['first_name'] . ' ' . $address['last_name']) . ', ';
        }
        
        if (!empty($address['address_1'])) {
            $formatted .= $address['address_1'] . ', ';
        }
        
        if (!empty($address['address_2'])) {
            $formatted .= $address['address_2'] . ', ';
        }
        
        if (!empty($address['city'])) {
            $formatted .= $address['city'] . ', ';
        }
        
        if (!empty($address['state'])) {
            $formatted .= $address['state'] . ' ';
        }
        
        if (!empty($address['postcode'])) {
            $formatted .= $address['postcode'] . ', ';
        }
        
        if (!empty($address['country'])) {
            $formatted .= $address['country'];
        }
        
        return rtrim($formatted, ', ');
    }
    
    /**
     * Get order details by order ID or number
     * 
     * @param string $order_identifier The order ID or order number
     * @return array|bool The order details or false if not found
     */
    public function get_order_by_identifier($order_identifier) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        // Try to get order by ID first
        $order = wc_get_order($order_identifier);
        
        // If not found, try to get by order number
        if (!$order) {
            // Query for orders with matching order number
            $orders = wc_get_orders(array(
                'limit' => 1,
                'return' => 'ids',
                'meta_key' => '_order_number',
                'meta_value' => $order_identifier
            ));
            
            if (!empty($orders)) {
                $order = wc_get_order($orders[0]);
            }
        }
        
        // If still not found, return false
        if (!$order) {
            return false;
        }
        
        // Get order items
        $order_items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            
            $order_items[] = array(
                'product_id' => $product ? $product->get_id() : 0,
                'name' => $product_name,
                'quantity' => $quantity,
                'total' => $item->get_total()
            );
        }
        
        // Return order details
        return array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'status' => $order->get_status(),
            'status_name' => wc_get_order_status_name($order->get_status()),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method_title(),
            'shipping_method' => $order->get_shipping_method(),
            'shipping_address' => $this->format_address($order->get_address('shipping')),
            'billing_address' => $this->format_address($order->get_address('billing')),
            'items' => $order_items
        );
    }
    
    /**
     * Get a summary of user orders for AI context
     * 
     * @param array $orders The user's orders
     * @return string A summary of the orders
     */
    public function get_orders_summary_for_ai($orders) {
        if (empty($orders)) {
            return "No order history available.";
        }
        
        $summary = "Here are the last " . count($orders) . " orders:\n\n";
        
        foreach ($orders as $index => $order) {
            $summary .= "Order #" . $order['order_number'] . " placed on " . date('F j, Y', strtotime($order['date_created'])) . "\n";
            $summary .= "Status: " . wc_get_order_status_name($order['status']) . "\n";
            $summary .= "Total: " . wc_price($order['total'], array('currency' => $order['currency'])) . "\n";
            
            // Add items
            $summary .= "Items: ";
            $items = array();
            foreach ($order['items'] as $item) {
                $items[] = $item['quantity'] . 'x ' . $item['name'];
            }
            $summary .= implode(', ', $items) . "\n\n";
        }
        
        return $summary;
    }
}