<?php

namespace Tests\Feature;

use App\Alias;
use App\Mail\SendFromEmail;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendFromEmailTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create(['username' => 'johndoe']);
        $this->user->recipients()->save($this->user->defaultRecipient);
    }

    /** @test */
    public function it_can_send_email_from_alias_from_file()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $alias = factory(Alias::class)->create([
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
        ]);

        $extension = 'contact=ebay.com';

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_send_from_alias.eml'),
                '--sender' => $this->user->defaultRecipient->email,
                '--recipient' => ['ebay+'.$extension.'@johndoe.anonaddy.com'],
                '--local_part' => ['ebay'],
                '--extension' => [$extension],
                '--domain' => ['johndoe.anonaddy.com'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => $alias->email,
            'local_part' => $alias->local_part,
            'domain' => $alias->domain,
            'emails_forwarded' => 0,
            'emails_blocked' => 0,
            'emails_replied' => 0,
            'emails_sent' => 1
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());

        Mail::assertQueued(SendFromEmail::class, function ($mail) {
            return $mail->hasTo('contact@ebay.com');
        });
    }

    /** @test */
    public function it_can_send_from_alias_to_multiple_emails_from_file()
    {
        Mail::fake();

        Mail::assertNothingSent();

        $alias = factory(Alias::class)->create([
            'user_id' => $this->user->id,
            'email' => 'ebay@johndoe.'.config('anonaddy.domain'),
            'local_part' => 'ebay',
            'domain' => 'johndoe.'.config('anonaddy.domain'),
        ]);

        $extension1 = 'contact=ebay.com';
        $extension2 = 'support=ebay.com';

        $this->artisan(
            'anonaddy:receive-email',
            [
                'file' => base_path('tests/emails/email_multiple_send_from.eml'),
                '--sender' => $this->user->defaultRecipient->email,
                '--recipient' => [
                    'ebay+'.$extension1.'@johndoe.anonaddy.com',
                    'ebay+'.$extension2.'@johndoe.anonaddy.com'
                ],
                '--local_part' => ['ebay', 'ebay'],
                '--extension' => [$extension1, $extension2],
                '--domain' => ['johndoe.anonaddy.com', 'johndoe.anonaddy.com'],
                '--size' => '1000'
            ]
        )->assertExitCode(0);

        $this->assertDatabaseHas('aliases', [
            'email' => $alias->email,
            'local_part' => $alias->local_part,
            'domain' => $alias->domain,
            'emails_forwarded' => 0,
            'emails_blocked' => 0,
            'emails_replied' => 0,
            'emails_sent' => 2
        ]);
        $this->assertEquals(1, $this->user->aliases()->count());

        Mail::assertQueued(SendFromEmail::class, function ($mail) {
            return $mail->hasTo('contact@ebay.com');
        });

        Mail::assertQueued(SendFromEmail::class, function ($mail) {
            return $mail->hasTo('support@ebay.com');
        });
    }
}
