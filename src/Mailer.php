<?php

declare(strict_types=1);

namespace AcmeCorptest\ReferenceExtension;

use Bolt\BoltForms\Event\PostSubmitEvent;
use Bolt\BoltForms\Factory\EmailFactory;
use Bolt\Common\Str;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Tightenco\Collect\Support\Collection;
//use Bolt\BoltForms\Event\BoltFormsEvents;
use Symfony\Component\Form\FormEvent;
//use Symfony\Component\Form\FormEvents;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class Mailer implements EventSubscriberInterface
{
    private $mailer;
	private $subjects = [
		'contact' => 'General Enquiry - Confirmation Email',
		'elc' => 'ELC Enquiry - Confirmation Email',
		'fdc' => 'Family Day Care - Confirmation Email',
		'courses' => 'Enrollment Enquiry - Confirmation Email'
	];

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function handleEvent(PostSubmitEvent $event): void
    {		
		$file = fopen("testmailer.txt","w");

		$form = $event->getForm();
		$data = $form->getData();		
		$meta = $event->getMeta();
		
        if (!$form->isValid()) {
			fwrite($file, $form->getName().'invalid');
            return;
        }		
		
		fwrite($file, 'valid');
		fclose($file);
		
        $email = (new TemplatedEmail())
            ->from($this->getFrom())
            ->to($data['email'])
            ->subject($this->subjects[$form->getName()])
            ->htmlTemplate('/forms/'.$form->getName().'_confirm_email.html.twig')
            ->context([
                'data' => $form->getData(),
                'formname' => $form->getName(),
                'meta' => $meta
            ]);		
			
		$this->mailer->send($email);
    }

    protected function getFrom(): Address
    {
        return $this->getAddress('shortcourses@vicsegnewfutures.org.au', 'VICSEG New Futures Website');
    }

    private function getAddress(string $email, string $name): Address
    {
        return new Address($email, $name);
    }

    public static function getSubscribedEvents()
    {
        return [
            'boltforms.post_submit' => ['handleEvent', 60],
        ];
    }
}
