<?php


namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run()
    {
        //     $userData = file_get_contents(base_path('database/data/user-data-with-password.json'));
        //     $data = json_decode($userData, true);
        //     $userAdmin = [[
        //         'name' => 'Admin 1',
        //         'email' => 'swapup.au@gmail.com',
        //         'password' => Hash::make('f5£aW?5nX$G0'),
        //         'role' => 'admin',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ], [
        //         'name' => 'Admin 2',
        //         'email' => 'adi@swapup.com.au',
        //         'password' => Hash::make("neDayEnSTroU"),
        //         'role' => 'admin',
        //         'created_at' => now(),
        //         'updated_at' => now(),
        //     ]];

        //     foreach ($userAdmin as $user) {
        //         array_push($data, $user);
        //     }
        //     foreach ($data as $user) {
        //         if (!isset($user['role'])) {
        //             $user['role'] = 'user';
        //             $user['password'] = Hash::make($user['temp_password']);
        //         }
        //         if ($user['email'] == 'alifa@swapup.com.au') {
        //             $user['role'] = 'admin';
        //             $user['password'] = Hash::make('RgaTEROvIErf');
        //         }
        //         unset($user['temp_password']);
        //         DB::table('users')->insert($user);
        //     }
        // }

        $userData = file_get_contents(base_path('database/data/user-new.json'));
        $data = json_decode($userData, true);
        Log::info('count data: ' . count($data));
        foreach ($data as $user) {
            DB::table('users')->where('email', $user['Email'])->update([
                'consign_id' => $user['Consign_id']
            ]);
        }
    }
}
