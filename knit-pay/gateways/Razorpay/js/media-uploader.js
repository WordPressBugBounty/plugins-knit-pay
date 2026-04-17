jQuery(document).ready(function($) {
	var razorpayMediaUploader;
	
	// Helper function to convert absolute URL to relative path
	function convertToRelativePath(absoluteUrl) {
		// If it's already a relative path or base64, return as is
		if (!absoluteUrl.startsWith('http://') && !absoluteUrl.startsWith('https://')) {
			return absoluteUrl;
		}

		try {
			var url = new URL(absoluteUrl);
			// Return path including domain-relative path
			return url.pathname;
		} catch (e) {
			// If URL parsing fails, return the original
			return absoluteUrl;
		}
	}

	// Helper function to convert relative path to absolute URL
	function convertToAbsoluteUrl(relativePath) {
		// If it's already an absolute URL or base64, return as is
		if (relativePath.startsWith('http://') || relativePath.startsWith('https://') || relativePath.startsWith('data:')) {
			return relativePath;
		}

		// Convert relative path to absolute URL using current site URL
		var siteUrl = window.location.origin;
		return siteUrl + relativePath;
	}

	// Handle upload button click
	$(document).on('click', '#razorpay_checkout_image_upload_button', function(e) {
		e.preventDefault();
		
		// If the uploader object has already been created, reopen the dialog
		if (razorpayMediaUploader) {
			razorpayMediaUploader.open();
			return;
		}
		
		// Create the media frame
		razorpayMediaUploader = wp.media({
			title: 'Select Checkout Image',
			button: {
				text: 'Use this image'
			},
			multiple: false,
			library: {
				type: 'image'
			}
		});
		
		// When an image is selected in the media frame
		razorpayMediaUploader.on('select', function() {
			var attachment = razorpayMediaUploader.state().get('selection').first().toJSON();
			
			// Convert to relative path for storage
			var relativePath = convertToRelativePath(attachment.url);

			// Set the relative path in the text field
			$('#_pronamic_gateway_razorpay_checkout_image').val(relativePath);
			
			// Show preview image using absolute URL
			var previewHtml = '<img src="' + attachment.url + '" style="max-width: 200px; max-height: 200px; margin-top: 10px; border: 1px solid #ddd; padding: 5px;" />';
			$('#razorpay_checkout_image_preview').html(previewHtml);
		});
		
		// Open the uploader dialog
		razorpayMediaUploader.open();
	});
	
	// Handle remove button click
	$(document).on('click', '#razorpay_checkout_image_remove_button', function(e) {
		e.preventDefault();
		$('#_pronamic_gateway_razorpay_checkout_image').val('');
		$('#razorpay_checkout_image_preview').html('');
	});
	
	// Show preview if image URL already exists
	var existingImageUrl = $('#_pronamic_gateway_razorpay_checkout_image').val();
	if (existingImageUrl && existingImageUrl.trim() !== '') {
		// Convert to absolute URL for display
		var displayUrl = convertToAbsoluteUrl(existingImageUrl);
		var previewHtml = '<img src="' + displayUrl + '" style="max-width: 200px; max-height: 200px; margin-top: 10px; border: 1px solid #ddd; padding: 5px;" />';
		$('#razorpay_checkout_image_preview').html(previewHtml);
	}
});
