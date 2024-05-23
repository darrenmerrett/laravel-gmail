<?php

namespace DarrenMerrett\LaravelGmail;

use Illuminate\Database\Eloquent\Model;

class gmail_tokens extends Model
{
	protected $fillable = [
		'userId',
		'email',
		'access_token',
		'expires_in',
		 'scope',
		'token_type',
		'created',
		'refresh_token',
	];

	public $table = 'gmail_tokens';

	public $timestamps = false;
}
