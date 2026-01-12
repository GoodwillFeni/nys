<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        User::updateOrCreate(
            ['email' => 'nkunziyenungu@gmail.com'],
            [
                'name' => 'Nkunziyenugu',
                'surname' => 'Sitsha',
                'password_hash' => Hash::make('Nkunziyenugu@123'),
                'is_super_admin' => true,
            ]
        );
    }
}
