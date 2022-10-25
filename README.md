## Apanio FB Api integration

This library push products from Laravel to Facebook catalogs of a given user, via Facebook API graph, every time a product changes.

## Usage

To push the products to facebook marketplace (one time):
- php artisan fb:catalog-sync

If you want to push products automatically in the background instead, execute the command:
- crontab -e

And add the following line:

\* \* \* \* \* cd /var/www/{APANIO_FOLDER}/ && php artisan schedule:run >> /dev/null 2>&1

## Disclaimer

This app has been developed for Apanio Marketplace.
