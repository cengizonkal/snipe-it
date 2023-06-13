<?php

namespace App\Console\Commands;

use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use League\Csv\Reader;

class ImportCheckouts extends Command
{

    protected $signature = 'snipeit:import-checkouts {filename}';

    protected $description = 'Import checkouts from a CSV file';


    public function handle()
    {
        $filename = $this->argument('filename');
        $csv = Reader::createFromPath(storage_path('private_uploads/imports/') . $filename, 'r');
        $this->info('Attempting to process: ' . storage_path('private_uploads/imports/') . $filename);
        $csv->setHeaderOffset(0); //because we don't want to insert the header
        $results = $csv->getRecords();
        $authUser = User::find(1);

        $this->output->progressStart(iterator_count($results));

        foreach ($results as $row) {
            $this->output->progressAdvance();
            try {
                // Check if the accessory exists
                if (is_null(
                    $accessory = Accessory::withCount('users as users_count')->where(
                        'name',
                        $row['Accessory Name']
                    )->first()
                )) {
                    // Redirect to the accessory management page with error
                    throw new \Exception('Accessory not found');
                }
                if (!$user = User::where('username', '=', $row['Username'])->first()) {
                    throw new \Exception('User not found');
                }

                if ($accessory->numRemaining() <= 0) {
                    throw new \Exception('No available accessories to checkout');
                }


                // Update the accessory data
                $accessory->assigned_to = $user->id;
                $accessory->users()->attach($accessory->id, [
                    'accessory_id' => $accessory->id,
                    'created_at' => Carbon::now(),
                    'user_id' => $authUser->id,
                    'assigned_to' => $user->id,
                    'note' => $row['Notes'],
                ]);
                event(new CheckoutableCheckedOut($accessory, $user, $authUser, $row['Notes']));
            } catch (\Exception $e) {
                $this->error('Error processing row: ' . $e->getMessage());
                continue;
            }
        }
        $this->output->progressFinish();
    }
}
