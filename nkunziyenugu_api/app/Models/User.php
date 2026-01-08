<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;


class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'surname',
        'email',
        'phone',
        'password_hash',
        'deleted_flag'
    ];

    protected $hidden = [
        'password_hash'
    ];

    //Get password attribute
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    //User assignments relationship
    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_users')
                    ->withPivot('role')
                    ->wherePivot('deleted_flag', 0);
    }
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    function addUserManually($pdo) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role']; // e.g., 'admin' or 'user'

            // Basic validation
            if (empty($username) || empty($email) || empty($password) || !in_array($role, ['admin', 'user'])) {
                echo "Invalid input.";
                return;
            }

            // Insert into database (adjust table/columns as needed)
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $password, $role])) {
                echo "User added successfully.";
            } else {
                echo "Error adding user.";
            }
        }
    }
}
