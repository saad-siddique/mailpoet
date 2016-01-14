<?php
namespace MailPoet\Models;

use MailPoet\Util\Helpers;
if(!defined('ABSPATH')) exit;

class Subscriber extends Model {
  public static $_table = MP_SUBSCRIBERS_TABLE;

  function __construct() {
    parent::__construct();

    $this->addValidations('email', array(
      'required' => __('You need to enter your email address.'),
      'isEmail' => __('Your email address is invalid.')
    ));
  }

  function segments() {
    return $this->has_many_through(
      __NAMESPACE__.'\Segment',
      __NAMESPACE__.'\SubscriberSegment',
      'subscriber_id',
      'segment_id'
    );
  }

  function delete() {
    // delete all relations to segments
    SubscriberSegment::where('subscriber_id', $this->id)->deleteMany();

    return parent::delete();
  }

  function addToSegments(array $segment_ids = array()) {
    $segments = Segment::whereIn('id', $segment_ids)->findMany();
    foreach($segments as $segment) {
      $association = SubscriberSegment::create();
      $association->subscriber_id = $this->id;
      $association->segment_id = $segment->id;
      $association->save();
    }
  }

  function sendConfirmationEmail() {
    $this->set('status', 'unconfirmed');

    // TODO
  }

  static function subscribe($subscriber_data = array(), $segment_ids = array()) {
    if(empty($subscriber_data) or empty($segment_ids)) {
      return false;
    }

    $subscriber = static::createOrUpdate($subscriber_data);

    if($subscriber !== false && $subscriber->id() > 0) {
      // restore deleted subscriber
      if($subscriber->deleted_at !== NULL) {
        $subscriber->setExpr('deleted_at', 'NULL');
      }

      if(Setting::hasSignupConfirmation()) {
        // reset status of existing subscribers if signup confirmation
        // is turned on
        if($subscriber->status !== 'subscribed') {
          $subscriber->sendConfirmationEmail();
        }
      } else {
        $subscriber->set('status', 'subscribed');
      }

      if($subscriber->save()) {
        $subscriber->addToSegments($segment_ids);
      }
    }

    return $subscriber;
  }

  static function search($orm, $search = '') {
    if(strlen(trim($search) === 0)) {
      return $orm;
    }

    return $orm->where_raw(
      '(`email` LIKE ? OR `first_name` LIKE ? OR `last_name` LIKE ?)',
      array('%'.$search.'%', '%'.$search.'%', '%'.$search.'%')
    );
  }

  static function filters() {
    $segments = Segment::orderByAsc('name')->findMany();
    $segment_list = array();
    $segment_list[] = array(
      'label' => __('All lists'),
      'value' => ''
    );

    foreach($segments as $segment) {
      $subscribers_count = $segment->subscribers()
        ->whereNull('deleted_at')
        ->count();
      if($subscribers_count > 0) {
        $segment_list[] = array(
          'label' => sprintf('%s (%d)', $segment->name, $subscribers_count),
          'value' => $segment->id()
        );
      }
    }

    $filters = array(
      'segment' => $segment_list
    );

    return $filters;
  }

  static function filterBy($orm, $filters = null) {
    if(empty($filters)) {
      return $orm;
    }
    foreach($filters as $key => $value) {
      if($key === 'segment') {
        $segment = Segment::findOne($value);
        if($segment !== false) {
          return $segment->subscribers();
        }
      }
    }
    return $orm;
  }

  static function groups() {
    return array(
      array(
        'name' => 'all',
        'label' => __('All'),
        'count' => Subscriber::getPublished()->count()
      ),
      array(
        'name' => 'subscribed',
        'label' => __('Subscribed'),
        'count' => Subscriber::filter('subscribed')->count()
      ),
      array(
        'name' => 'unconfirmed',
        'label' => __('Unconfirmed'),
        'count' => Subscriber::filter('unconfirmed')->count()
      ),
      array(
        'name' => 'unsubscribed',
        'label' => __('Unsubscribed'),
        'count' => Subscriber::filter('unsubscribed')->count()
      ),
      array(
        'name' => 'trash',
        'label' => __('Trash'),
        'count' => Subscriber::getTrashed()->count()
      )
    );
  }

  static function groupBy($orm, $group = null) {
    if($group === 'trash') {
      return $orm->whereNotNull('deleted_at');
    } else if($group === 'all') {
      return $orm->whereNull('deleted_at');
    } else {
      return $orm->filter($group);
    }
  }

  static function filterWithCustomFields($orm) {
    $orm = $orm->select(MP_SUBSCRIBERS_TABLE.'.*');
    $customFields = CustomField::findArray();
    foreach ($customFields as $customField) {
      $orm = $orm->select_expr(
        'IFNULL(GROUP_CONCAT(CASE WHEN ' .
        MP_CUSTOM_FIELDS_TABLE . '.id=' . $customField['id'] . ' THEN ' .
        MP_SUBSCRIBER_CUSTOM_FIELD_TABLE . '.value END), NULL) as "' . $customField['name'].'"');
    }
    $orm = $orm
      ->leftOuterJoin(
        MP_SUBSCRIBER_CUSTOM_FIELD_TABLE,
        array(MP_SUBSCRIBERS_TABLE.'.id', '=',
          MP_SUBSCRIBER_CUSTOM_FIELD_TABLE.'.subscriber_id'))
      ->leftOuterJoin(
        MP_CUSTOM_FIELDS_TABLE,
        array(MP_CUSTOM_FIELDS_TABLE.'.id','=',
          MP_SUBSCRIBER_CUSTOM_FIELD_TABLE.'.custom_field_id'))
      ->groupBy(MP_SUBSCRIBERS_TABLE.'.id');
    return $orm;
  }

  static function filterWithCustomFieldsForExport($orm) {
    $orm = $orm->select(MP_SUBSCRIBERS_TABLE.'.*');
    $customFields = CustomField::findArray();
    foreach ($customFields as $customField) {
      $orm = $orm->selectExpr(
        'CASE WHEN ' .
        MP_CUSTOM_FIELDS_TABLE . '.id=' . $customField['id'] . ' THEN ' .
        MP_SUBSCRIBER_CUSTOM_FIELD_TABLE . '.value END as "' . $customField['id'].'"');
    }
    $orm = $orm
      ->leftOuterJoin(
        MP_SUBSCRIBER_CUSTOM_FIELD_TABLE,
        array(MP_SUBSCRIBERS_TABLE.'.id', '=',
          MP_SUBSCRIBER_CUSTOM_FIELD_TABLE.'.subscriber_id'))
      ->leftOuterJoin(
        MP_CUSTOM_FIELDS_TABLE,
        array(MP_CUSTOM_FIELDS_TABLE.'.id','=',
          MP_SUBSCRIBER_CUSTOM_FIELD_TABLE.'.custom_field_id'));
    return $orm;
  }

  function customFields() {
    return $this->hasManyThrough(
      __NAMESPACE__.'\CustomField',
      __NAMESPACE__.'\SubscriberCustomField',
      'subscriber_id',
      'custom_field_id'
    )->select_expr(MP_SUBSCRIBER_CUSTOM_FIELD_TABLE.'.value');
  }

  static function createOrUpdate($data = array()) {
    $subscriber = false;

    if(isset($data['id']) && (int)$data['id'] > 0) {
      $subscriber = static::findOne((int)$data['id']);
      unset($data['id']);
    }

    if($subscriber === false && !empty($data['email'])) {
      $subscriber = static::where('email', $data['email'])->findOne();
      if($subscriber !== false) {
        unset($data['email']);
      }
    }

    if($subscriber === false) {
      $subscriber = static::create();
      $subscriber->hydrate($data);
    } else {
      $subscriber->set($data);
    }

    $subscriber->save();
    return $subscriber;
  }

  static function bulkMoveToList($orm, $data = array()) {
    $segment_id = (isset($data['segment_id']) ? (int)$data['segment_id'] : 0);
    $segment = Segment::findOne($segment_id);
    if($segment !== false) {
      $subscribers = $orm->findResultSet();
      foreach($subscribers as $subscriber) {
        // remove subscriber from all segments
        SubscriberSegment::where('subscriber_id', $subscriber->id)->deleteMany();

        // create relation with segment
        $association = SubscriberSegment::create();
        $association->subscriber_id = $subscriber->id;
        $association->segment_id = $segment->id;
        $association->save();
      }
      return array(
        'subscribers' => $subscribers->count(),
        'segment' => $segment->name
      );
    }
    return false;
  }

  static function bulkRemoveFromList($orm, $data = array()) {
    $segment_id = (isset($data['segment_id']) ? (int)$data['segment_id'] : 0);
    $segment = Segment::findOne($segment_id);

    if($segment !== false) {
      // delete relations with segment
      $subscribers = $orm->findResultSet();
      foreach($subscribers as $subscriber) {
        SubscriberSegment::where('subscriber_id', $subscriber->id)
          ->where('segment_id', $segment->id)
          ->deleteMany();
      }

      return array(
        'subscribers' => $subscribers->count(),
        'segment' => $segment->name
      );
    }
    return false;
  }

  static function bulkRemoveFromAllLists($orm) {
    $segments = Segment::findMany();
    $segment_ids = array_map(function($segment) {
      return $segment->id();
    }, $segments);

    if(!empty($segment_ids)) {
      // delete relations with segment
      $subscribers = $orm->findResultSet();
      foreach($subscribers as $subscriber) {
        SubscriberSegment::where('subscriber_id', $subscriber->id)
          ->whereIn('segment_id', $segment_ids)
          ->deleteMany();
      }

      return $subscribers->count();
    }
    return false;
  }

  static function bulkConfirmUnconfirmed($orm) {
    $subscribers = $orm->findResultSet();
    $subscribers->set('status', 'subscribed')->save();
    return $subscribers->count();
  }

  static function bulkResendConfirmationEmail($orm) {
    $subscribers = $orm
      ->where('status', 'unconfirmed')
      ->findResultSet();

    if(!empty($subscribers)) {
      foreach($subscribers as $subscriber) {
        $subscriber->sendConfirmationEmail();
      }

      return $subscribers->count();
    }
    return false;
  }

  static function bulkAddToList($orm, $data = array()) {

    $segment_id = (isset($data['segment_id']) ? (int)$data['segment_id'] : 0);
    $segment = Segment::findOne($segment_id);

    if($segment !== false) {
      $subscribers_count = 0;
      $subscribers = $orm->findMany();
      foreach($subscribers as $subscriber) {
        // create relation with segment
        $association = \MailPoet\Models\SubscriberSegment::create();
        $association->subscriber_id = $subscriber->id;
        $association->segment_id = $segment->id;
        if($association->save()) {
          $subscribers_count++;
        }
      }
      return array(
        'subscribers' => $subscribers_count,
        'segment' => $segment->name
      );
    }
    return false;
  }

  static function subscribed($orm) {
    return $orm
      ->whereNull('deleted_at')
      ->where('status', 'subscribed');
  }

  static function unsubscribed($orm) {
    return $orm
      ->whereNull('deleted_at')
      ->where('status', 'unsubscribed');
  }

  static function unconfirmed($orm) {
    return $orm
      ->whereNull('deleted_at')
      ->where('status', 'unconfirmed');
  }

  static function createMultiple($columns, $values) {
    return self::rawExecute(
      'INSERT INTO `' . self::$_table . '` ' .
      '(' . implode(', ', $columns) . ') ' .
      'VALUES ' . rtrim(
        str_repeat(
          '(' . rtrim(str_repeat('?,', count($columns)), ',') . ')' . ', '
          , count($values)
        )
        , ', '),
      Helpers::flattenArray($values)
    );
  }

  static function updateMultiple($columns, $subscribers, $currentTime = false) {
    $ignoreColumnsOnUpdate = array(
      'email',
      'created_at'
    );
    $subscribers = array_map('array_values', $subscribers);
    $emailPosition = array_search('email', $columns);
    $sql =
      function ($type) use (
        $columns,
        $subscribers,
        $emailPosition,
        $ignoreColumnsOnUpdate
      ) {
        return array_filter(
          array_map(function ($columnPosition, $columnName) use (
            $type,
            $subscribers,
            $emailPosition,
            $ignoreColumnsOnUpdate
          ) {
            if(in_array($columnName, $ignoreColumnsOnUpdate)) return;
            $query = array_map(
              function ($subscriber) use ($type, $columnPosition, $emailPosition) {
                return ($type === 'values') ?
                  array(
                    $subscriber[$emailPosition],
                    $subscriber[$columnPosition]
                  ) :
                  'WHEN email = ? THEN ?';
              }, $subscribers);
            return ($type === 'values') ?
              Helpers::flattenArray($query) :
              $columnName . '= (CASE ' . implode(' ', $query) . ' END)';
          }, array_keys($columns), $columns)
        );
      };
    return self::rawExecute(
      'UPDATE `' . self::$_table . '` ' .
      'SET ' . implode(', ', $sql('statement')) . ' '.
      (($currentTime) ? ', updated_at = "' . $currentTime . '" ' : '') .
        'WHERE email IN ' .
        '(' . rtrim(str_repeat('?,', count($subscribers)), ',') . ')',
      array_merge(
        Helpers::flattenArray($sql('values')),
        Helpers::arrayColumn($subscribers, $emailPosition)
      )
    );
  }
}