phpush
======


h2. Configuration

1. Checkout repo 
2. Put pem certificate somewhere on the server.
3. Create database and import tables.sql
4. Create configuration folder based on default config and populate configuration file


h2. Usage

* Register Device. GET Request http://server-url.com/index.php?q=register_token&uid=123&token=502f601dd0078

* Send Push Notification. GET Request http://server-url.com/index.php?q=send_message&uid=123&message=messagetText

* Cleanup expired tokens; GET Request http://server-url.com/index.php?q=cleanup_tokens

h2. Return values

JSON in format 
@{"status":"success","message":"description"}@
or
@{"status":"error","message":"description"}@
