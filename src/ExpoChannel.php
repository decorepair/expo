<?php

namespace NotificationChannels\Expo;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use NotificationChannels\Expo\Exceptions\CouldNotSendNotification;

class ExpoChannel
{
    public function __construct(protected string $accessToken)
    {
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     *
     * @return mixed
     * @throws CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        if (! ($to = $notifiable->routeNotificationFor('expo'))) {
            throw CouldNotSendNotification::noValidDestination($notifiable);
        }

        if (! method_exists($notification, 'toExpo')) {
            throw CouldNotSendNotification::undefinedMethod($notification);
        }

        /** @var ExpoMessage $message */
        if (! ($message = $notification->toExpo($notification)) instanceof ExpoMessage) {
            throw CouldNotSendNotification::couldNotCreateMessage($notification);
        }

        if (! is_array($to)) {
            $to = [$to];
        }

        $messages = array_map(
            function ($recipient) use ($message) {
                return array_merge(['to' => $recipient], $message->toArray());
            },
            $to
        );

        try {
            $response = $this->client()->post('/', $messages)->throw();

            if ($response->status() !== 200) {
                throw CouldNotSendNotification::serviceRespondedWithAnError($response);
            }

            return $response;
        } catch (RequestException $exception) {
            throw CouldNotSendNotification::requestException($exception);
        } catch (Exception $exception) {
            throw CouldNotSendNotification::unexpectedException($exception);
        }
    }

    /**
     * @return PendingRequest
     */
    protected function client(): PendingRequest
    {
        return Http::asJson()
            ->baseUrl('https://exp.host/--/api/v2/push/send')
            ->withToken($this->accessToken);
    }
}
