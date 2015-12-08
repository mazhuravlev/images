Installation
==========
* composer install
* cp .env.example .env
* cd public
* npm install
* bower install

Install Redis
============

Add following lines to .env file
===================
CACHE_TTL=600
CACHE_PREFIX=images:url:

Run server
===========
php artisan serve