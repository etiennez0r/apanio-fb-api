<?php

namespace App\ApanioFBApi;

use App\ApanioFBApi\FBDBProcessor as FBApiDB;
use App\ApanioFBApi\FBApiProcessor as FBApi;

/**
 * High level class, handles all the logic to update/delete products in facebook catalogs.
 */
class FBCatalogProcessor
{
    /**
     * Get the state of the open handles from facebook and update the product with the new information.
     * @return array
     */
    public static function updateLastBatchResults() : array
    {
        $products = FBApiDB::getProductsWithOpenHandle();
        $withErrors = 0;
        $finished = 0;

        foreach ($products as $product) {
            $result = FBApi::queryHandleResult($product);
            FBApiDB::updateProductHandle($product, $result);

            if (isset($result['status']) && $result['status'] == 'finished')
                $finished++;

            if (isset($result['errors_total_count']) && $result['errors_total_count'] > 0)
                $withErrors++;
        }

        return [count($products), $finished, $withErrors];
    }

    /**
     * Push the products to their user catalog in facebook.
     * 
     * @return int
     */
    public static function pushProducts2FB() : int
    {
        $products = FBApiDB::getPendingProducts2Push();

        foreach ($products as $product) {
            $handle = FBApi::pushProductToUserCatalog($product, $product->user);
            FBApiDB::saveBatchRequestHandle($product, $handle);
        }

        return count($products);
    }

    /**
     * Deletes the products that have been soft deleted in laravel, from the user catalog in facebook.
     * 
     * @return int
     */
    public static function deleteProducts() : int
    {
        $products = FBApiDB::getDeletedProducts();

        foreach ($products as $product) {
            $handle = FBApi::deleteProductFromUserCatalog($product, $product->user);
            FBApiDB::saveBatchRequestHandle($product, $handle);
        }

        return count($products);
    }
}
