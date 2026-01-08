<?php
use MailerSend\MailerSend;
use MailerSend\Helpers\Builder\Personalization;
        use MailerSend\Helpers\Builder\Recipient;
        use MailerSend\Helpers\Builder\EmailParams;

        $mailersend = new MailerSend(['Api_Token_Here' => 'key']);

$personalization = [
    new Personalization('recipient@email.com', [
            'name' => ''
    ])
];

        $recipients = [
            new Recipient('recipient@email.com', 'Recipient'),
        ];

        $emailParams = (new EmailParams())
            ->setFrom('info@domain.com')
            ->setFromName('Your Name')
            ->setRecipients($recipients)
            ->setSubject('Subject')
            ->setTemplateId('z86org8yxr1lew13')
    ->setPersonalization($personalization);

        $mailersend->email->send($emailParams);
