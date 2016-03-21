<?php
namespace MailPoet\Cron\Workers;

use Carbon\Carbon;
use MailPoet\Cron\CronHelper;
use MailPoet\Models\Newsletter;
use MailPoet\Models\Segment;
use MailPoet\Models\SendingQueue;
use MailPoet\Models\SubscriberSegment;
use MailPoet\Util\Helpers;
use Cron\CronExpression as Cron;

if(!defined('ABSPATH')) exit;

class Scheduler {
  public $timer;

  function __construct($timer = false) {
    $this->timer = ($timer) ? $timer : microtime(true);
    CronHelper::checkExecutionTimer($this->timer);
  }

  function process() {
    $scheduled_queues = SendingQueue::where('status', 'scheduled')
      ->whereLte('scheduled_at', Carbon::now()->format('Y-m-d H:i:s'))
      ->findMany();
    if(!count($scheduled_queues)) return;
    foreach($scheduled_queues as $queue) {
      $newsletter = Newsletter::filter('filterWithOptions')
        ->findOne($queue->newsletter_id);
      if(!$newsletter) {
        $queue->delete();
      } elseif($newsletter->type === 'welcome') {
        $this->processWelcomeNewsletter($newsletter, $queue);
      } elseif($newsletter->type === 'notification') {
        $this->processPostNotificationNewsletter($newsletter, $queue);
      }
      CronHelper::checkExecutionTimer($this->timer);
    }
  }

  function processWelcomeNewsletter($newsletter, $queue) {
    $subscriber = unserialize($queue->subscribers);
    $subscriber_in_segment =
      SubscriberSegment::where('subscriber_id', $subscriber['to_process'][0])
        ->where('segment_id', $newsletter->segment)
        ->findOne();
    if(!$subscriber_in_segment) {
      $queue->delete();
    } else {
      $queue->status = null;
      $queue->save();
    }
  }

  function processPostNotificationNewsletter($newsletter, $queue) {
    $subscriber_ids = array();
    $segments = Segment::whereIn('id', unserialize($newsletter->segments))
      ->findMany();
    foreach($segments as $segment) {
      $subscriber_ids = array_merge(
        $subscriber_ids,
        Helpers::arrayColumn(
          $segment->subscribers()->findArray(),
          'id'
        )
      );
    }
    if(empty($subscriber_ids)) return;
    // TODO: check if newsletter contents changed since last time it was sent
    $subscriber_ids = array_unique($subscriber_ids);
    $queue->subscribers = serialize(
      array(
        'to_process' => $subscriber_ids
      )
    );
    $queue->count_total = $queue->count_to_process = count($subscriber_ids);
    $queue->status = null;
    $queue->save();
    $new_queue = SendingQueue::create();
    $new_queue->newsletter_id = $newsletter->id;
    $schedule = Cron::factory($newsletter->schedule);
    $new_queue->scheduled_at = $schedule->getNextRunDate()->format('Y-m-d H:i:s');
    $new_queue->status = 'scheduled';
    $new_queue->save();
  }
}