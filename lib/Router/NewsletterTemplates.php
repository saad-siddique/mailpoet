<?php
namespace MailPoet\Router;

use MailPoet\Models\NewsletterTemplate;

if(!defined('ABSPATH')) exit;

class NewsletterTemplates {
  function __construct() {
  }

  function get($data = array()) {
    $id = (isset($data['id'])) ? (int) $data['id'] : 0;
    $template = NewsletterTemplate::findOne($id);
    if($template === false) {
      wp_send_json(false);
    } else {
      $template->body = json_decode($template->body);
      wp_send_json($template->asArray());
    }
  }

  function getAll() {
    $collection = NewsletterTemplate::findArray();
    $collection = array_map(function($item) {
      $item['body'] = json_decode($item['body']);
      return $item;
    }, $collection);
    wp_send_json($collection);
  }

  function save($data = array()) {
    if (isset($data['body'])) {
      $data['body'] = json_encode($data['body']);
    }

    $result = NewsletterTemplate::createOrUpdate($data);
    if($result !== true) {
      wp_send_json($result);
    } else {
      wp_send_json(true);
    }
  }

  function delete($id) {
    $template = NewsletterTemplate::findOne($id);
    if($template !== false) {
      $result = $template->delete();
    } else {
      $result = false;
    }
    wp_send_json($result);
  }
}
