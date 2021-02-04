<?php
declare(strict_types=1);

namespace Neos\Fusion\Form\Runtime\Action;

/*
 * This file is part of the Neos.Fusion.Form package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Form\Exception\FinisherException;
use Neos\Fusion\Form\Runtime\Domain\Exception\ActionException;
use Neos\Fusion\Form\Runtime\Domain\ActionInterface;
use Neos\SwiftMailer\Message as SwiftMailerMessage;
use Neos\Utility\MediaTypes;
use Psr\Http\Message\UploadedFileInterface;

class EmailAction implements ActionInterface
{

    /**
     * @param array $options
     * @return string|null
     * @throws ActionException
     * @throws FinisherException
     */
    public function handle(array $options = []): ?ActionResponse
    {
        if (!class_exists(SwiftMailerMessage::class)) {
            throw new ActionException('The "neos/swiftmailer" doesn\'t seem to be installed, but is required for the EmailFinisher to work!', 1503392532);
        }

        $subject = $options['subject'] ?? null;
        $text = $options['text'] ?? null;
        $html = $options['html'] ?? null;

        $recipientAddress = $options['recipientAddress'] ?? null;
        $recipientName = $options['recipientName'] ?? null;
        $senderAddress = $options['senderAddress'] ?? null;
        $senderName = $options['senderName'] ?? null;
        $replyToAddress = $options['replyToAddress'] ?? null;
        $carbonCopyAddress = $options['carbonCopyAddress'] ?? null;
        $blindCarbonCopyAddress = $options['blindCarbonCopyAddress'] ?? null;

        $testMode = $options['testMode'] ?? false;

        if ($subject === null) {
            throw new ActionException('The option "subject" must be set for the EmailFinisher.', 1327060320);
        }
        if ($recipientAddress === null) {
            throw new ActionException('The option "recipientAddress" must be set for the EmailFinisher.', 1327060200);
        }
        if (is_array($recipientAddress) && !empty($recipientName)) {
            throw new ActionException('The option "recipientName" cannot be used with multiple recipients in the EmailFinisher.', 1483365977);
        }
        if ($senderAddress === null) {
            throw new ActionException('The option "senderAddress" must be set for the EmailFinisher.', 1327060210);
        }

        $mail = new SwiftMailerMessage();

        $mail
            ->setFrom($senderName ? array($senderAddress => $senderName) : $senderAddress)
            ->setSubject($subject);

        if (is_array($recipientAddress)) {
            $mail->setTo($recipientAddress);
        } else {
            $mail->setTo($recipientName ? array($recipientAddress => $recipientName) : $recipientAddress);
        }

        if ($replyToAddress !== null) {
            $mail->setReplyTo($replyToAddress);
        }

        if ($carbonCopyAddress !== null) {
            $mail->setCc($carbonCopyAddress);
        }

        if ($blindCarbonCopyAddress !== null) {
            $mail->setBcc($blindCarbonCopyAddress);
        }

        if ($text !== null && $html !== null) {
            $mail->setBody($html, 'text/html');
            $mail->addPart($text, 'text/plain');
        } elseif ($text !== null) {
            $mail->setBody($text, 'text/plain');
        } elseif ($html !== null) {
            $mail->setBody($html, 'text/html');
        }

        $this->addAttachments($mail, $options);

        if ($testMode === true) {
            $response = new ActionResponse();
            $response->setContent(
                \Neos\Flow\var_dump(
                    array(
                        'sender' => array($senderAddress => $senderName),
                        'recipients' => is_array($recipientAddress) ? $recipientAddress : array($recipientAddress => $recipientName),
                        'replyToAddress' => $replyToAddress,
                        'carbonCopyAddress' => $carbonCopyAddress,
                        'blindCarbonCopyAddress' => $blindCarbonCopyAddress,
                        'text' => $text,
                        'html' => $html
                    ),
                    'E-Mail "' . $subject . '"',
                    true
                )
            );
            return $response;
        } else {
            $mail->send();
        }

        return null;
    }

    /**
     * @param SwiftMailerMessage $mail
     * @param array $options
     */
    protected function addAttachments(SwiftMailerMessage $mail, array $options)
    {
        $attachments = $options['attachments'] ?? null;
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_string($attachment)) {
                    $mail->attach(\Swift_Attachment::fromPath($attachment));
                    continue;
                } elseif (is_object($attachment) && ($attachment instanceof UploadedFileInterface)) {
                    $mail->attach(new \Swift_Attachment($attachment->getStream()->getContents(), $attachment->getClientFilename(), $attachment->getClientMediaType()));
                    continue;
                } elseif (is_object($attachment) && ($attachment instanceof PersistentResource)) {
                    $mail->attach(new \Swift_Attachment(stream_get_contents($attachment->getStream()), $attachment->getFilename(), $attachment->getMediaType()));
                    continue;
                } elseif (is_array($attachment) && isset($attachment['content']) && isset($attachment['name'])) {
                    $content = $attachment['content'];
                    $name = $attachment['name'];
                    $type =  $attachment['type'] ?? MediaTypes::getMediaTypeFromFilename($name);
                    $mail->attach(new \Swift_Attachment($content, $name, $type));
                    continue;
                }
            }
        }
    }
}
