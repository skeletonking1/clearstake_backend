<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head></head>
<body>
	<p>Hi {{ $first_name }}, You have been granted access to the Casper token portal. Please <a href="{{ $link }}">click here</a> to activate your account and claim your Casper.</p>
</body>
</html>