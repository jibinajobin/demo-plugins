<?php

if (! defined('ABSPATH')) {
    exit;
}

class Evodent_Order_Documents_Email
{

    public function __construct()
    {
        add_action(
            'wp_ajax_evodent_send_documents',
            array($this, 'send_documents')
        );
    }

    public function send_documents()
    {
        check_ajax_referer(
            'evodent_documents',
            'nonce'
        );

        $order_id     = absint($_POST['order_id'] ?? 0);
        $order_number = sanitize_file_name($_POST['order_number'] ?? $order_id);

        if (! $order_id) {
            wp_send_json_error('Invalid order.');
        }

        if (empty($_FILES['documents'])) {
            wp_send_json_error('No files uploaded.');
        }

        $order = wc_get_order($order_id);

        if (! $order) {
            wp_send_json_error('Order not found.');
        }

        $customer_email = $order->get_billing_email();
        $customer_name  = trim(
            $order->get_billing_first_name() . ' ' .
                $order->get_billing_last_name()
        );

        if (empty($customer_name)) {
            $customer_name = $order->get_formatted_billing_full_name();
        }

        if (empty($customer_name)) {
            $customer_name = 'Customer';
        }

        $upload_dir  = wp_upload_dir();
        $destination = $upload_dir['basedir']
            . '/evodent-order-documents/order-'
            . $order_number;

        wp_mkdir_p($destination);

        $saved       = array();
        $attachments = array();

        if (isset($_FILES['documents']['name']) && is_array($_FILES['documents']['name'])) {
            foreach ($_FILES['documents']['name'] as $key => $name) {
                $tmp      = $_FILES['documents']['tmp_name'][$key] ?? '';
                $filename = sanitize_file_name($name);

                if (empty($filename) || empty($tmp) || ! is_uploaded_file($tmp)) {
                    continue;
                }

                // Security check: Only allow PDF files
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    continue;
                }

                $filepath = $destination . '/' . $filename;

                if (move_uploaded_file($tmp, $filepath)) {
                    $saved[]       = $filename;
                    $attachments[] = $filepath;
                }
            }
        }

        if (empty($attachments)) {
            wp_send_json_error('No valid PDF files were uploaded.');
        }

        // Logo URL fallback
        $logo = home_url('/wp-content/uploads/2025/10/Evodent-Logo.png');

        $message = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 0;">
<tr>
<td align="center">

<img src="' . esc_url($logo) . '" alt="Evodent"
     style="max-width:300px;margin-bottom:18px;display:block;">

<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border:1px solid #ddd;">

<tr>
<td style="background:#e53935;color:#fff;padding:36px 48px;font-size:30px; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;font-weight: 300;line-height: 150%;">
Order Documents - #' . esc_html($order->get_order_number()) . '
</td>
</tr>

<tr>
<td style="padding:40px;color: #636363;font-size:14px;line-height: 150%; font-family: \'Helvetica Neue\', Helvetica, Roboto, Arial, sans-serif;">

<p>Dear ' . esc_html($customer_name) . ',</p>

<p>
Please find the documents for Order <strong>#' . esc_html($order->get_order_number()) . '</strong> in this email.
</p>

<p>Thank you for shopping with us.</p>

</td>
</tr>

<tr>
<td align="center" style="padding:25px;font-size:12px; line-height: 125%;  color: #ef8886;border-top:1px solid #eee; font-family: Arial;">

Evodent – Dental Models &amp; Simulators<br>
Jaypee General Agencies, West Hill, Calicut 673005, Kerala, India.

</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8'
        );

        $sent = wp_mail(
            $customer_email,
            'Order Documents for Order #' . $order->get_order_number(),
            $message,
            $headers,
            $attachments
        );

        if (! $sent) {
            wp_send_json_error('Email could not be sent.');
        }

        // Save sent document history
        $sent_documents = get_post_meta($order_id, '_evodent_sent_documents', true);

        if (! is_array($sent_documents)) {
            $sent_documents = array();
        }

        foreach ($saved as $filename) {
            $sent_documents[] = array(
                'file' => $filename,
                'url'  => trailingslashit($upload_dir['baseurl']) . 'evodent-order-documents/order-' . $order_number . '/' . $filename,
                'sent' => current_time('mysql'),
            );
        }

        update_post_meta(
            $order_id,
            '_evodent_sent_documents',
            $sent_documents
        );

        wp_send_json_success(array(
            'files' => $saved,
            'email' => $customer_email,
        ));
    }
}

new Evodent_Order_Documents_Email();
