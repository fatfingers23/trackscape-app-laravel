<?php

namespace App\Jobs;

use App\Models\ChatLog;
use App\Models\Clan;
use App\Services\WebhookService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\ParameterBag;

class NewChatHandlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Clan $clan;
    protected array $chatLog;
    private WebhookService $webhookService;


    public function __construct(Clan $clan, array $chatLog, WebhookService $webhookService)
    {
        $this->clan = $clan;
        $this->chatLog = $chatLog;
        $this->webhookService = $webhookService;
    }

    public function handle()
    {
        $messageId = substr($this->chatLog["id"], 0, -3);
        $newChat = new ChatLog();
        $newChat->time_sent = Carbon::now('UTC');
//                    $newChat->time_sent = Carbon::parse($chatLog["timestamp"]);
        $newChat->sender = $this->chatLog["sender"];
        $newChat->message = $this->chatLog["message"];
        $newChat->clan_id = $this->clan->id;
        $newChat->chat_id = $messageId;
        if ($this->clan->save_chat_logs) {
            $newChat->save();
        }

        $collectionLogMatches = [];
        $collectionLogMatch = preg_match('/(.*)received a new collection log item: [^0-9]*(.*)\)$/',
            $newChat->message, $collectionLogMatches);
        if ($collectionLogMatch == 1) {
            RecordCollectionLogJob::dispatch($this->webhookService, $this->clan, $collectionLogMatches);
        }

        $personalBestMatches = [];
        $personalBestMatch = preg_match('/(.*) has achieved a new (.*) personal best: (.*)/',
            $newChat->message, $personalBestMatches);
        if ($personalBestMatch == 1) {
            PersonalBestJob::dispatch($this->clan, $personalBestMatches);
        }
        $lowerCaseMessage = strtolower($newChat->message);
        if (str_starts_with($lowerCaseMessage, '!pb')) {
            PersonalBestCommandJob::dispatch($newChat)->delay(3);
        }

        $this->webhookService->sendSimpleMessage($this->clan, $newChat);
    }
}