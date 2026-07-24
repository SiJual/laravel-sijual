<?php

namespace App\Services\Social;

use App\Models\PublishJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PublishSchedulerService
{
    public function __construct(private MetaOAuthService $meta) {}

    /**
     * Schedule a content to be published at a specific time.
     */
    public function schedule(int $contentId, string $platform, Carbon $scheduledAt): PublishJob
    {
        return PublishJob::create([
            'content_id' => $contentId,
            'platform' => $platform,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);
    }

    /**
     * Process pending jobs that are due for publishing.
     * This method is meant to be called by Laravel Scheduler.
     */
    public function processPendingJobs(): void
    {
        $jobs = PublishJob::where('status', 'pending')
                          ->where('scheduled_at', '<=', now())
                          ->get();

        foreach ($jobs as $job) {
            $this->executePublish($job);
        }
    }

    private function executePublish(PublishJob $job): void
    {
        try {
            $job->update(['status' => 'processing']);
            
            $content = $job->contentAsset;
            $user = $job->user;

            if ($job->platform === 'instagram') {
                $igUserId = $user->instagram_user_id;
                $accessToken = $user->meta_access_token;
                
                if (!$igUserId || !$accessToken) {
                    throw new \Exception('User has not connected their Instagram account.');
                }

                $success = $this->meta->publishToInstagram(
                    $igUserId, 
                    $accessToken, 
                    $content->image_url, 
                    $content->caption
                );

                $job->update([
                    'status' => $success ? 'completed' : 'failed',
                    'error_message' => $success ? null : 'Failed to publish via Graph API.',
                ]);
            } else {
                // Future expansion for other platforms
                $job->update(['status' => 'failed', 'error_message' => 'Platform not supported.']);
            }

        } catch (\Exception $e) {
            Log::error('PublishSchedulerService: Error executing job', ['job_id' => $job->id, 'error' => $e->getMessage()]);
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
