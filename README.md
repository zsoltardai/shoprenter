# Shoprenter project

You can find the project using the following link: https://shoprenter-project.herokuapp.com/

## /api-docs

The <b>/api-docs</b> makes you able to test the api using the swagger UI.
You can find the <b>/api-docs</b> route with the following link:
https://shoprenter-project.herokuapp.com/api-docs

## API endpoints

### /secret

The <b>/secret</b> route is able to receive a POST request with the Content-Type: application/x-www-form-urlencoded.
You can also specify the Accept header by setting the Accept header of your request either to application/json or application/xml.
If you set the Accept header to anything else, it will automatically send you the response in application/xml formating.

#### example:

```
POST /secret HTTP/1.1
Accept: application/json
Content-Type: application/x-www-form-urlencoded
User-Agent: PostmanRuntime/7.29.0
Postman-Token: d86c5ca9-0151-4912-bf5a-e7161898ff73
Host: shoprenter-project.herokuapp.com
Accept-Encoding: gzip, deflate, br
Connection: keep-alive
Content-Length: 57

secret=Hello%20world%21&expireAfter=10&expireAfterViews=3

HTTP/1.1 200 OK
Connection: keep-alive
Date: Sat, 25 Jun 2022 12:49:48 GMT
Server: Apache
Transfer-Encoding: chunked
Content-Type: text/html; charset=UTF-8
Via: 1.1 vegur

{
    "hash": "0dccc858d035e43db5f935c6fc1e0bb9",
    "secretText": "Hello world!",
    "createdAt": "2022-06-25 14:49:48",
    "expiresAt": "2022-06-25 14:59:48",
    "remainingViews": 3,
    "key": "ZlZSajNKRTBCY3N1VW5OVUxrVWZTc1RmdnBIOHcvZndCNXlzNURyWA"
}
```

### /secret/{hash}

The <b>/secret/{hash}</b> endpoint waits for a GET request and if it finds a secret with the provided hash
it tries to decrypt it with the provided encryption key which is sent in the Authorization header.
If the provided key was not valid, or it wasn't provided at all, the api returns with an error.

#### example

As you can see in the following example, we are sending a GET request to the <b>/secret/0dccc858d035e43db5f935c6fc1e0bb9</b>
endpoint, and we are receiving a response in application/xml format, as we requested in the Accept header.

As you can see we provided the encryption key in the Authorization header. It did not return an Error,
so we can be sure about that the encryption key was valid.

```
GET /secret/0dccc858d035e43db5f935c6fc1e0bb9 HTTP/1.1
Authorization: ZlZSajNKRTBCY3N1VW5OVUxrVWZTc1RmdnBIOHcvZndCNXlzNURyWA
Accept: application/xml
User-Agent: PostmanRuntime/7.29.0
Postman-Token: c0ee682b-43c4-45fd-925a-3b0fa8af9f79
Host: shoprenter-project.herokuapp.com
Accept-Encoding: gzip, deflate, br
Connection: keep-alive

HTTP/1.1 200 OK
Connection: keep-alive
Date: Sat, 25 Jun 2022 12:54:46 GMT
Server: Apache
Transfer-Encoding: chunked
Content-Type: text/html; charset=UTF-8
Via: 1.1 vegur

<?xml version="1.0" encoding="UTF-8"?>
<Secret>
    <hash>0dccc858d035e43db5f935c6fc1e0bb9</hash>
    <secretText>Hello world!</secretText>
    <createdAt>2022-06-25 14:49:48</createdAt>
    <expiresAt>2022-06-25 14:59:48</expiresAt>
    <remainingViews>2</remainingViews>
    <key>ZlZSajNKRTBCY3N1VW5OVUxrVWZTc1RmdnBIOHcvZndCNXlzNURyWA</key>
</Secret>
```

## Encryption

The api uses <b>aes-256-cbc</b> encryption and stores the encrypted text in the database.Y
You can find the code used for the encryption in the <b>utils.php</b> file.

## Database

For this project I'm using <b>ClearDB MySQL</b> which is provided free by Heroku.

### secrets table

The <b>secrets</b> table is created by using this SQL code:

```mysql
CREATE TABLE IF NOT EXISTS secrets(
    hash VARCHAR(128) PRIMARY KEY NOT NULL,
    secret LONGTEXT NOT NULL,
    createdAt DATETIME NOT NULL,
    expiresAt DATETIME NOT NULL,
    remainingViews INTEGER(11) NOT NULL
);
```

As you can see the hash attribute is a primary key, which is a 128 long VARCHAR, and can store an md5 hash.
The type of the secret attribute is LONGTEXT due to we don't know how long will the provided secret be.
The createdAt and the expiresAt parameters are DATETIME instances.
The remainingViews parameter is an INTEGER, and it stores the remaining views.

To make sure that the records will be automatically deleted after the expiredAt time, I've created a PROCEDURE and an EVENT
which runs every second, and removes the records which has been expired.

```mysql
CREATE PROCEDURE remove_expired_secrets()
BEGIN
    DECLARE finished INTEGER DEFAULT 0;
    DECLARE current_hash VARCHAR(128) DEFAULT '';
    DECLARE HashesCursor CURSOR FOR SELECT hash FROM secrets WHERE expiresAt < SYSDATE();
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
    OPEN HashesCursor;
    remove_expired_loop: LOOP
        FETCH HashesCursor INTO current_hash;
        IF finished = 1 THEN
            LEAVE remove_expired_loop;
        END IF;
        DELETE FROM secrets WHERE hash = current_hash;
    END LOOP remove_expired_loop;
    CLOSE HashesCursor;
END;
```

```mysql
CREATE EVENT remove_expired_secrets ON SCHEDULE EVERY 1 SECOND DO CALL remove_expired_secrets();
```

## models/SecretModel

The <b>SecretModel</b> class is used for communicating with the database.
Its constructor receives a mysqli connection object as a parameter, and uses it to
SELECT, DELETE, INSERT and UPDATE records in the <b>secrets</b> table.

## .env

There is a <b>.env</b> file which holds the credentials used to connect to the database,
but this is not included in this Github repository.

## .htaccess

The <b>.htaccess</b> file is used to rewrite the rules on the php server.

In order to make it possible to send a request to an endpoint in the following formats:

```
    https://shoprenter-project.herokuapp.com/secret
    
    https://shoprenter-project.herokuapp.com/secert/0dccc858d035e43db5f935c6fc1e0bb9
```

I had to rewrite some rules in the .htaccess file.

```html
<IfModule mod_rewrite.c>

    RewriteEngine On

    RewriteCond %{REQUEST_FILENAME} !-d

    RewriteCond %{REQUEST_FILENAME}\.php -f

    RewriteRule ^secret/([a-f\d]{32}|[A-F\d]{32}) secret.php?hash=$1 [NC,L]

    RewriteRule secret secret.php [NC,L]

</IfModule>
```
