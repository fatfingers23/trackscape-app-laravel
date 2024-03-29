<?php

namespace App\Jobs;

use App\Models\ChatLog;
use App\Models\Clan;
use App\Services\ChatLogPatterns;
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
        $string = htmlentities($this->chatLog["sender"], ENT_HTML5, 'utf-8');
        $content = str_replace("&nbsp;", " ", $string);
        $cleanName = html_entity_decode($content);
        $newChat->sender = $cleanName;
        $newChat->message = $this->chatLog["message"];
        $newChat->clan_id = $this->clan->id;
        $newChat->chat_id = $messageId;

        if ($this->clan->save_chat_logs) {
            $newChat->save();
        }


        $collectionLogMatches = [];
        $collectionLogMatch = preg_match(ChatLogPatterns::$collectionLogPattern,
            $newChat->message, $collectionLogMatches);
        if ($collectionLogMatch == 1) {
            RecordCollectionLogJob::dispatch($this->webhookService, $this->clan, $collectionLogMatches);
        }

        $personalBestMatches = [];
        $personalBestMatch = preg_match(ChatLogPatterns::$personalBestPattern,
            $newChat->message, $personalBestMatches);
        if ($personalBestMatch == 1) {
            PersonalBestJob::dispatch($this->clan, $personalBestMatches);
        }
        $lowerCaseMessage = strtolower($newChat->message);
        if (str_starts_with($lowerCaseMessage, '!pb')) {
            PersonalBestCommandJob::dispatch($newChat)->delay(3);
        }
        $rankOfUser = $this->clan->members()->where('username', $newChat->sender)?->first()?->rank;

        $this->webhookService->sendSimpleMessage($this->clan, $newChat, $cleanName, $rankOfUser);

        DropLogParser::dispatch($this->clan, $newChat->message);
    }
}