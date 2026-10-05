<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'WC_Email' ) ) {
	return;
}


class Evodent_WC_Email_Customer_Documents extends WC_Email {

	public function __construct() {

		$this->id             = 'evodent_customer_documents';
		$this->customer_email = true;

		$this->title       = 'Customer Documents';
		$this->description = 'Send uploaded documents to the customer.';

		$this->heading = 'Your Order Documents';
		$this->subject = 'Documents for Order #{order_number}';

		$this->template_html  = 'emails/customer-documents.php';
		$this->template_plain = 'emails/plain/customer-documents.php';
		$this->template_base  = EVODENT_DOCS_PATH . 'templates/';

		parent::__construct();
	}

	public function trigger( $order_id, $attachments = array() ) {

		if ( ! $order_id ) {
			return;
		}

		$this->object = wc_get_order( $order_id );

		if ( ! $this->object ) {
			return;
		}

		$this->recipient = $this->object->get_billing_email();

		$this->attachments = $attachments;

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);
	}

	public function get_subject() {

		return str_replace(
			'{order_number}',
			$this->object->get_order_number(),
			$this->subject
		);
	}

	public function get_content_html() {

		ob_start();

		wc_get_template(
			$this->template_html,
			array(
				'email_heading' => $this->get_heading(),
				'order'         => $this->object,
				'email'         => $this,
			),
			'',
			$this->template_base
		);

		return ob_get_clean();
	}

	public function get_content_plain() {

		return '';
	}

	public function get_content() {

		return $this->get_content_html();
	}

	public function get_attachments() {

		return $this->attachments;
	}
}