<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Message extends Model
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['user_id', 'user_name', 'message', 'created_at', 'updated_at'];


   
 
     public function user()
     {
         return $this->belongsTo(User::class);
     }
 
     
 
}
