<?php
if (! defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}
?>

<h2>Kindly read this carefully before proceeding.</h2>
<hr>
<ol>

	<li>We rely on the third-party service provider RapidAPI for the setup
		of Knit Pay Pro. RapidAPI will charge you based on your monthly usage.
	</li>

	<li>Choose a plan that suits your usage. Each plan includes a certain
		number of free transactions. If you exceed the transaction quota,
		additional charges will apply for receiving extra transactions. <br>
		<?php
        if (defined('KNIT_PAY_UPI')) {
            include_once KNIT_PAY_UPI_DIR . 'views/template-knit-pay-upi-pricing-table.php';
        }
        ?>
		<br>

		<?php
        if (defined('KNIT_PAY_PRO')) {
            include_once KNIT_PAY_PRO_DIR . 'views/template-knit-pay-pro-pricing-table.php';
        }
        ?>
		<br>
	</li>

	<li>Obtain your Rapid API keys. If you need assistance generating
		RapidAPI keys, you can refer to the link below. <br> <a
		target="_blank"
		href="https://docs.rapidapi.com/docs/keys-and-key-rotation#creating-or-rotating-a-rapid-api-key">https://docs.rapidapi.com/docs/keys-and-key-rotation#creating-or-rotating-a-rapid-api-key</a>
	</li>

	<li>Enter your RapidAPI key below and save the settings.</li>

	<li>Feel free to <a target="_blank"
		href="https://www.knitpay.org/contact-us/">contact us</a> if you need
		help.
	</li>

</ol>
<hr>

<style>
li, dd {
	margin-bottom: 18px;
}
</style>
