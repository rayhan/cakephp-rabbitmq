<?php
/**
 * CakePHP-RabbitMQ Lib File
 *
 * Proxy class to RabbitMQ
 *
 * PHP version 7
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Elisio Leonardo <elisio.leonardo@gmail.com>
 * @copyright     Copyright 2016, Elisio Leonardo <elisio.leonardo@gmail.com>
 * @link          http://infomoz.net
 * @package       RabbitMQ
 * @subpackage      RabbitMQ.Lib
 * @since         0.2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Folder', 'Utility');
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * CakePHP-RabbitMQ Integration
 *
 *
 */
class RabbitMQ
{


    /**
     * Envia uma Mensagem para o RabbitMQ
     *
     * @param string $queue Name of the queue to enqueue the job to.
     * @param string $class Class of the job.
     * @param array $args Arguments passed to the job.
     * @param boolean $trackStatus Whether to track the status of the job.
     * @return string Job Id.
     */
    public static function publish($message, $options = [])
    {
        $options = array_merge([
            'exchange'     => 'router',
            'queue'        => 'default',
            'delay_queue'   => 'delay_default',
            'delay_exchange' => 'delay_router',
            'delay'        => false,
            'delay_time'    => 120,
            'exchange_type'=>'direct'
        ], $options);
        $conn = new AMQPConnection(RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASS, RABBITMQ_VHOST);
        $ch = $conn->channel();
        $ch->queue_declare($options['queue'], false, true, false, false);
        $ch->exchange_declare($options['exchange'], $options['exchange_type'], false, true, false);
        $ch->queue_bind($options['queue'], $options['exchange']);

        $msg_body = json_encode($message);
        $msg = new PhpAmqpLib\Message\AMQPMessage($msg_body, [
            'content_type'  => 'application/json',
            'delivery_mode' => 2,
        ]);

        if ($options['delay']) {
            $ch->queue_declare(
                $options['delay_queue'],
                false,
                true,
                false,
                false,
                false,
                [
                    'x-message-ttl'          => ['I', $options['delay_time'] * 1000],
                    // delay in seconds to milliseconds
                    "x-expires"              => ['I', $options['delay_time'] * 1000 + 1000],
                    'x-dead-letter-exchange' => ['S', $options['exchange']]
                ]
            );
            $ch->exchange_declare($options['delay_exchange'], 'direct',false,true,false);
            $ch->queue_bind($options['delay_queue'], $options['delay_exchange']);
            $ch->basic_publish($msg, $options['delay_exchange']);
        } else{
            $ch->basic_publish($msg, $options['exchange']);
        }

        $ch->close();
        $conn->close();
    }

}