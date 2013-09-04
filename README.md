# Fuel Background Package.

This package allows you to create the process in your app context.

# Usage


	Background::forge(function() {

		// very large process will run background.

	})->run();
	
	

Example:

	// You need to set driver to 'sendmail' in config/mail.php
	// mail() doesn't work correctly in exec() context..
	$mail = Email::forge();
	
	// Too many to addresses..
	$mail->to(array(
		'mail1@fuel-background.com',
		'mail2@fuel-background.com',
		'mail3@fuel-background.com',
		'mail4@fuel-background.com',
		'mail5@fuel-background.com',
		'mail6@fuel-background.com',
	));

	$background = Background::forge(function($mail) {

		$mail->send();

	}, array($mail));

	// attach the listener of event.
	$background->on('before', function() {
		\Log::info('send mail start.');
	});

	$background->on('success', function() {
		\Log::info('send mail success.');
	});

	// exception handler will be called with Exception object.
	$background->on('exception', function($e) {
		\Log::info('the exception "'.get_class($e).'" was thrown.');
	});

	$background->run();


Events:
	
	- before
		before the process starts

	- after
		after the process ends

	- success
		when the process end with true return value.

	- error
		when the process end with false return value.

	- exception
		when the process throw some exceptions.