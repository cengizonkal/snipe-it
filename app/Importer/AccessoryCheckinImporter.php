<?php

namespace App\Importer;

use App\Events\CheckoutableCheckedIn;
use App\Models\Accessory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccessoryCheckinImporter extends ItemImporter
{
    public function __construct($filename)
    {

        parent::__construct($filename);

    }

    protected function handle($row)
    {

        parent::handle($row); // TODO: Change the autogenerated stub
        $this->checkin($row);
    }

    /**
     * Create an accessory if a duplicate does not exist
     *
     * @author Daniel Melzter
     * @since 3.0
     */
    public function checkin($row)
    {

        //find accessory from name
        $accessory = Accessory::where('name', $row['accessory name'])->first();

        //find user from username
        $user = User::where('username', $row['username'])->first();

        //find accessory_user from accessory_id and user_id
        $accessory_user = DB::table('accessories_users')->where([
            ['accessory_id', '=', $accessory->id],
            ['assigned_to', '=', $user->id],
        ])->first();

        // Check if the accessory exists
        if (is_null($accessory_user)) {
            // Redirect to the accessory management page with error
            $this->log('Accessory ' . $row['accessory name'] . ' was not assigned to ' . $row['username'] . '.  ');
            return;
        }
        $checkin_at = date('Y-m-d');


        // Was the accessory updated?
        if (DB::table('accessories_users')->where('id', '=', $accessory_user->id)->delete()) {
            $return_to = e($accessory_user->assigned_to);
            event(new CheckoutableCheckedIn($accessory, User::find($return_to), Auth::user(), $row['notes'], $checkin_at));
            $this->log('Accessory ' . $row['accessory name'] . ' was checked in.');
            return;
        }
        $this->logError($accessory, 'Accessory');

    }
}
