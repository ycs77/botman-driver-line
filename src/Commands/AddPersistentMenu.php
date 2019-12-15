<?php

namespace BotMan\Drivers\Line\Commands;

use BotMan\BotMan\Http\Curl;
use Illuminate\Console\Command;

class AddPersistentMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'botman:line:AddMenu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a persistent Line menu';

    /**
     * @var Curl
     */
    private $http;

    /**
     * Create a new command instance.
     *
     * @param Curl $http
     */
    public function __construct(Curl $http)
    {
        parent::__construct();
        $this->http = $http;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $payload = ['persistent_menu' => config('botman.line.persistent_menu')];

        if (! $payload) {
            $this->error('You need to add a Line menu payload data to your BotMan Line config in line.php.');
            exit;
        }

        $response = $this->http->post('https://graph.line.com/v3.0/me/messenger_profile?access_token='.config('botman.line.token'),
            [], $payload);

        $responseObject = json_decode($response->getContent());

        if ($response->getStatusCode() == 200) {
            $this->info('Line menu was set.');
        } else {
            $this->error('Something went wrong: '.$responseObject->error->message);
        }
    }
}
