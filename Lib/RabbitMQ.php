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
 * @subpackage	  RabbitMQ.Lib
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
class RabbitMQ {



/**
 * Envia uma Mensagem para o CakeRabbit
 *
 * @param string $queue Name of the queue to enqueue the job to.
 * @param string $class Class of the job.
 * @param array $args Arguments passed to the job.
 * @param boolean $trackStatus Whether to track the status of the job.
 * @return string Job Id.
 */
	public static function publish($message,$exchange='router',$queue='default') {
        $conn = new AMQPConnection(RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASS, RABBITMQ_VHOST);
        $ch = $conn->channel();


        /*
            name: $queue
            passive: false
            durable: true // the queue will survive server restarts
            exclusive: false // the queue can be accessed in other channels
            auto_delete: false //the queue won't be deleted once the channel is closed.
        */
        $ch->queue_declare($queue, false, true, false, false);

        /*
            name: $exchange
            type: direct
            passive: false
            durable: true // the exchange will survive server restarts
            auto_delete: false //the exchange won't be deleted once the channel is closed.
        */

        $ch->exchange_declare($exchange, 'direct', false, true, false);

        $ch->queue_bind($queue, $exchange);

        $msg_body = implode(' ', array_slice($message,0));
        $msg = new PhpAmqpLib\Message\AMQPMessage($msg_body, array('content_type' => 'text/plain',
                                                                 'delivery_mode' => 2));
        $ch->basic_publish($msg, $exchange);

        $ch->close();
        $conn->close();
	}

}