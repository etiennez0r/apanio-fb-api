<?php

namespace App\ApanioFBApi;

use Exception;
use Illuminate\Database\Eloquent\Model;
use FacebookAds\Api as FBApi;
use FacebookAds\Http\RequestInterface;
use Illuminate\Support\Facades\Log;

/**
 * Low level class, handles all the queries to facebook Api Graph related to product catalogs.
 */
class FBApiProcessor
{
    private const APP_ID = '675670963915785';                           // replace with Apanio App ID
    private const APP_SECRET = '39ba7094e3bcdeee0f067ba119e922b3';      // replace with Apanio App Secret
    private const API_VERSION = '15.0';                                 // set to current meta Api Graph Version

    // required fields to send in the product push to FB and their related field in products table:
    private const REQUIRED_CATALOG_FIELDS = [

        //  'FB_CATALOG_PRODUCT_FIELD' => 'MY_ORM_FIELD',

        'name'                         => 'name',
        'brand'                        => '',
        'price'                        => 'price',
        'currency'                     => '',
        'description'                  => 'description',
        'image_url'                    => '',
        'url'                          => '',
        'inventory'                    => 'stock',
    ];

    // some default values in case the product doesn't have value so the product push can pass with success
    private const DEFAULT_VALUES = [

        //  'FB_CATALOG_PRODUCT_FIELD'  => 'default_value'

        'brand'                     => 'Apanio',
        'price'                     => '0',
        'currency'                  => 'CLP',
        'description'               => 'Zapatos Adidas Puremotion',
        'image_url'                 => 'https://apanio.com/assets/img/logo-150-2.png',
        'url'                       => 'https://apanio.com/busqueda?producto=&categoria=17',
    ];

    /**
     * Set the parameters to make api request on behalf a FB user.
     *
     * @param string $accessToken
     * @return \FacebookAds\Api
     */
    public static function connect(string $accessToken)
    {
        FBApi::init(self::APP_ID, self::APP_SECRET, $accessToken);

        $api = FBApi::instance();

        $api->setDefaultGraphVersion(self::API_VERSION);

        return $api;
    }

    /**
     * Retrieves the catalog ID of a FB user given by his $accessToken
     * Api graph endopoint:  /me/businesses/?fields=owned_product_catalogs
     *
     * @param string $accessToken
     * @return string
     */
    public static function queryCatalogID(?string $accessToken) : ?string
    {
        $catalogID = null;

        if (!$accessToken)
            return $catalogID;

        $api = self::connect($accessToken);

        try {
            $result = $api->call(
                "/me/businesses/",
                RequestInterface::METHOD_GET,
                ['fields' => ['owned_product_catalogs']]
            );

            $parsedResult = $result->getContent();

            if (
                isset($parsedResult['data']) &&
                isset($parsedResult['data'][0]) &&
                isset($parsedResult['data'][0]['owned_product_catalogs']['data']) &&
                isset($parsedResult['data'][0]['owned_product_catalogs']['data'][0]) &&
                isset($parsedResult['data'][0]['owned_product_catalogs']['data'][0]['id'])
            )
                $catalogID = $parsedResult['data'][0]['owned_product_catalogs']['data'][0]['id'];
        } catch (Exception $e) {
            $msg = __METHOD__ . ' dio error: ' . $e->getMessage();
            Log::error($msg);
        }

        return $catalogID;
    }

    /**
     * Push a product to a given $catalogID
     * 
     * Api graph endopoint: /POST/v15.0/{catalogID}/batch
     * 
     * Returns the handle string, or null if no success.
     * @param Model $product
     * @param Model $user
     * @param bool $delete
     * @return string
     */
    public static function pushProductToUserCatalog(Model $product, Model $user = null, bool $delete = false) : ?string
    {
        $fields = [];
        $handle = null;
        $method = "CREATE";

        $catalogID = $user->fb_catalog_id;
        $accessToken = $user->fb_access_token;

        if (!$catalogID || !$accessToken)
            return $handle;

        if ($delete)
            $method = "DELETE";

        // prepare fields for create or update operations, not required for deletions
        if (!$delete) {
            foreach (self::REQUIRED_CATALOG_FIELDS as $catalogField => $dbField) {
                if (isset($product->$dbField) && $product->$dbField)
                    $fields[$catalogField] = $product->$dbField;
                else if (isset(self::DEFAULT_VALUES[$catalogField]))
                    $fields[$catalogField] = self::DEFAULT_VALUES[$catalogField];

                if ($catalogField == 'price')
                    $fields[$catalogField] *= 100;
            }
        }

        $payload = [
            "requests" => [
                [
                    "method" => $method,
                    "retailer_id" => $product->id,
                    "data" => $fields
                ]
            ]
        ];

        $api = self::connect($accessToken);

        try {
            $result = $api->call(
                "/$catalogID/batch",
                RequestInterface::METHOD_POST,
                $payload
            );

            $parsedResult = $result->getContent();

            if (isset($parsedResult['handles'])) {
                $handle = $parsedResult['handles'][0];
            } else {
                $msg = __METHOD__ . ' no se pudo determinar un handle para la operacion..';

                if (
                    isset($parsedResult['validation_status']) &&
                    isset($parsedResult['validation_status'][0]) &&
                    isset($parsedResult['validation_status'][0]['errors'])
                )

                    $msg = __METHOD__ . ': ' . $parsedResult['validation_status'][0]['errors'][0]['message'];

                Log::error($msg . " (productid: $product->id)");
            }
        } catch (Exception $e) {
            $msg = __METHOD__ . ' dio error: ' . $e->getMessage();
            Log::error($msg);
        }

        return $handle;
    }

    /**
     * DELETES the product from the a given $catalogID
     * 
     * Api graph endopoint: /POST/v15.0/{catalogID}/batch
     * 
     * Returns the handle string given by the result of the Api, or null if no delete was made.
     * @param Model $product
     * @param string $accessToken
     * @param string $catalogID
     * @return string
     */
    public static function deleteProductFromUserCatalog(Model $product, Model $user) : ?string
    {
        $handle = self::pushProductToUserCatalog($product, $user, true);

        return $handle;
    }

    /**
     * Checks if the $accessToken corresponds to a given $userID
     * 
     * Api graph endopoint: /GET/v15.0/me
     * 
     * @param string $accessToken
     * @param string $userID
     * @return bool
     */
    public static function checkAccessToken(?string $accessToken, ?string $userID) : bool
    {
        $valid = false;

        if (!$accessToken || !$userID)
            return $valid;

        $api = self::connect($accessToken);

        try {
            $result = $api->call(
                '/me',
                RequestInterface::METHOD_GET
            );

            $parsedResult = $result->getContent();

            $valid = (isset($parsedResult['id']) && $parsedResult['id'] == $userID);
        } catch (Exception $e) {
            $msg = __METHOD__ . ' dio error: ' . $e->getMessage();
            Log::error($msg);
        }

        return $valid;
    }

    /**
     * Gets result of a previous update request of a product.
     * 
     * Api graph endopoint: /GET/v15.0/{catalogID}/check_batch_request_status
     * 
     * @param Model $product
     * @return ?string
     */
    public static function queryHandleResult(Model $product) : ?array
    {
        $ret = null;

        $accessToken = $product->user?->fb_access_token;
        $catalogID = $product->user?->fb_catalog_id;
        $handle = $product->fb_handle;

        if (!$accessToken || !$catalogID || !$handle)
            return $ret;

        $api = self::connect($accessToken);

        try {
            $result = $api->call(
                "/$catalogID/check_batch_request_status",
                RequestInterface::METHOD_GET,
                ['handle' => $handle]
            );

            $parsedResult = $result->getContent();

            if (isset($parsedResult['data']) && isset($parsedResult['data'][0]))
                $ret = $parsedResult['data'][0];
        } catch (Exception $e) {
            $msg = __METHOD__ . ' dio error: ' . $e->getMessage();
            Log::error($msg);
        }

        return $ret;
    }
}
