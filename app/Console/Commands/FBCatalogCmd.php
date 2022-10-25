<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ApanioFBApi\FBCatalogProcessor;

class FBCatalogCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fb:catalog-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize products to facebook catalogs, get results from previous synchronizations.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Updating last batch results..");
        list($totals, $finished, $errors) = FBCatalogProcessor::updateLastBatchResults();
        $this->info("$totals total handles updated to database ($finished finished, $errors with errors).");

        $this->info("Pushing new products to facebook..");
        $totals = FBCatalogProcessor::pushProducts2FB();
        $this->info("$totals products pushed.");
        
        $this->info("Deleting products from facebook..");
        $totals = FBCatalogProcessor::deleteProducts();
        $this->info("$totals products deleted.");
    }
}
