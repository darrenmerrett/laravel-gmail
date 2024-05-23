<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class GmailTokens extends Migration
{
	public function up()
	{
		Schema::create('gmail_tokens', function (Blueprint $table) {
			$table->increments('id')->unsigned();
			$table->integer('userId')->unsigned();
			$table->string('email', 320);
			$table->string('access_token', 500);
			$table->integer('expires_in')->unsigned();
			$table->text('scope');
			$table->string('token_type', 50);
			$table->integer('created')->unsigned();
			$table->string('refresh_token', 500);
		});
	}

	public function down()
	{
		Schema::drop('gmail_tokens');
	}
}
