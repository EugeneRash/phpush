phpush
======

Configuration

1. Checkout repo 
2. Put pem certificate somewhere on the server. (Here is detailed tutorial about pem files generation http://www.raywenderlich.com/32960/apple-push-notification-services-in-ios-6-tutorial-part-1)
3. Create database and import tables.sql
4. Create configuration folder (for ex. sites/server-url.com) based on default config and populate configuration file

Usage
* Register Device. GET Request http://server-url.com/index.php?q=register_token&uid=123&token=502f601dd0078

* Send Push Notification. GET Request http://server-url.com/index.php?q=send_message&uid=123&message=SomeMessageText

* Cleanup expired tokens; GET Request http://server-url.com/index.php?q=cleanup_tokens

Return values

JSON in format 
{"status":"success","message":"description"}

or

{"status":"error","message":"description"}
