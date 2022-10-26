<?php

namespace App\ApanioFBApi;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use Carbon\Carbon;

/**
 * Low level class, handles all interactions with the Laravel database.
 */
class FBDBProcessor
{
    /**
     * Saves the handle given by facebook api graph
     * 
     * @param Model $product
     * @param ?string $handle
     * @return void
     */
    public static function saveBatchRequestHandle(Model $product, ?string $handle) : void
    {
        if (!$handle)
            return;

        if ($product && $product->fb_handle != $handle) {
            $product->fb_handle = $handle;
            $product->fb_sync_status = 'started';
            $product->fb_sync_errors_total = 0;
            $product->fb_sync_errors = null;

            $product->fb_synced_at = Carbon::now()->add(1, 'second')->format('Y-m-d H:i:s');

            $product->fb_sync_attempts++;

            $product->save();
        }
    }

    /**
     * Get all the products that have a non finished batch request
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getProductsWithOpenHandle()
    {
        $query = Product::query();

        $query->whereNotNull('fb_handle')
            ->where(function ($query) {
                return $query->whereNull('fb_sync_status')
                    ->orWhere('fb_sync_status', '<>', 'finished');
            });

        return $query->get();
    }

    /**
     * Update the handle request of a product according to the values received in the array $handleResult
     * 
     * @param Model $product
     * @param ?array $handleResult
     * @return void
     */
    public static function updateProductHandle(Model $product, ?array $handleResult) : void
    {
        if (!$product || !$handleResult)
            return;

        if (!isset($handleResult['status']) || $product->fb_sync_status == $handleResult['status'])
            return;

        $product->fb_sync_status = $handleResult['status'];
        $product->fb_sync_errors_total = $handleResult['errors_total_count'];

        if ($handleResult['errors_total_count'] > 0)
            $product->fb_sync_errors = serialize($handleResult['errors']);
        else
            $product->fb_sync_errors = 0;

        if (!self::productTouched($product) || $product->fb_synced_at == null)
            // keeping fb_synced_at ahead of updated_at so this product don't appear as pending to update product.
            $product->fb_synced_at = Carbon::now()->add(1, 'second')->format('Y-m-d H:i:s');
        // if the last update happened after the last sync we let the outdated fb_synced_at value so it will appear as pending to update.

        $product->save();
    }

    /**
     * Get all the products that requires a push to the catallog, based on the following conditions:
     * 
     * - Have an associated user with a facebook access token
     * - Are not deleted
     * - Have never been synced OR, (the last sync is in 'finished' state AND outdated regarding the last updated_at)
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getPendingProducts2Push()
    {
        $query = Product::query();

        $query->select('products.*')

            ->join('users', 'products.user_id', '=', 'users.id')
            ->whereNotNull('users.fb_access_token')

            ->where('products.deleted', '=', 0)

            ->where(function ($query) {

                return $query->whereNull('fb_synced_at')
                             ->orWhere(function ($query) {
                                    return $query->whereRaw('fb_synced_at < products.updated_at')
                                                ->where('fb_sync_status', '=', 'finished');
                                });
            });

        return $query->get();
    }

    /**
     * Get all the deleted products that requires a deletion from the catallog, based on the following conditions:
     * 
     * - Have an associated user with a facebook access token
     * - Has been soft deleted
     * - The last sync is in 'finished' state
     * - The last sync is outdated regarding the last updated_at a.k.a. the deletion itself.
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public static function getDeletedProducts()
    {
        $query = Product::query();

        $query->select('products.*')

            ->join('users', 'products.user_id', '=', 'users.id')
            ->whereNotNull('users.fb_access_token')

            ->where('products.deleted', '=', 1)

            ->where('fb_sync_status', '=', 'finished')

            ->where(function ($query) {
                return $query->whereRaw('fb_synced_at is null or fb_synced_at < products.updated_at');
            });

        return $query->get();
    }

    /**
     * Check if the product have been touched since the last sync.
     * 
     * @param Model $product
     * @return bool
     */
    public static function productTouched(Model $product) : bool
    {
        return ($product->updated_at > $product->fb_synced_at);
    }
}
