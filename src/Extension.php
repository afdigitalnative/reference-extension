<?php

declare(strict_types=1);

namespace AcmeCorptest\ReferenceExtension;

use Bolt\Extension\BaseExtension;
use Symfony\Component\Routing\Route;
use Symfony\Component\Form\FormInterface;
use Bolt\BoltForms\Event\BoltFormsEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Bolt\BoltForms\Event\BoltFormsEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Extension extends BaseExtension
{
	private $messages = [];
	private $file = null;
	
    /**
     * Return the full name of the extension
     */
    public function getName(): string
    {
        return 'Test Extension';
    }

    /**
     * Add the routes for this extension.
     *
     * Note: These are cached by Symfony. If you make modifications to this, run
     * `bin/console cache:clear` to ensure your routes are parsed.
     */
    public function getRoutes(): array
    {
		/*
        return [
            'reference' => new Route(
                '/extensions/reference/{name}',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::index'],
                ['name' => '[a-zA-Z0-9]+']
            ),
        ];
		*/
		
        return [
            'getOccurrences' => new Route(
                '/extensions/reference/getOccurrences',
                [
					'_controller' => 'AcmeCorptest\ReferenceExtension\Controller::index'
				],
				['name' => '[a-zA-Z0-9]+']
            ),
            'getOccurExtendedDetail' => new Route(
                '/extensions/reference/getOccurExtendedDetail',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::getOccurExtendedDetail'],
                ['name' => '[a-zA-Z0-9]+']
            ),
            'getStudentDetail' => new Route(
                '/extensions/reference/getStudentDetail',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::getStudentDetail'],
                ['name' => '[a-zA-Z0-9]+']
            ),
            'registerStudent' => new Route(
                '/extensions/reference/registerStudent',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::registerStudent'],
                ['name' => '[a-zA-Z0-9]+']
            ),
            'proceedPayment' => new Route(
                '/extensions/reference/proceedPayment',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::proceedPayment'],
                ['name' => '[a-zA-Z0-9]+']
            ),
            'applyPromo' => new Route(
                '/extensions/reference/applyPromo',
                ['_controller' => 'AcmeCorptest\ReferenceExtension\Controller::applyPromo'],
                ['name' => '[a-zA-Z0-9]+']
            ),				
        ];		
    }

    /**
     * Ran automatically, if the current request is in a browser.
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initialize($cli = false): void
    {
        $this->addWidget(new ReferenceWidget());

        $this->addTwigNamespace('reference-extension');

        $this->addListener('kernel.response', [new EventListener(), 'handleEvent']);
		$this->addListener(BoltFormsEvents::POST_SET_DATA, array($this, 'populateCourses'));	
		$this->addListener(BoltFormsEvents::PRE_SUBMIT, array($this, 'sendToVetrack'));
    
	}

	public function populateCourses(FormEvent $event): void
	{
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
			
		if($form->getName() === 'courses') {
			$courseSelect = $form->get('courseselect');
			$courseSelectOptions = $courseSelect->getConfig()->getOptions();
			$courses = iterator_to_array($this->getQuery()->getContent('main-courses')->getCurrentPageResults());
			$courseSelectOptions['choices'] = [];
						
			foreach($courses as $course) {
				$courseSelectOptions['choices'][$course->getFieldValue('title')] = $course->getFieldValue('title');	
			}

			$form->add('courseselect', ChoiceType::class, $courseSelectOptions);
		}		
	
	}
	
    public function sendToVetrack(FormEvent $event): void
    {
		$vetrack_username = '';
		$vetrack_password = '';
		$config = $this->getConfig();
		
		if ($config->has('vetrack_api') && isset($config->get('vetrack_api')['username'])) {
			$vetrack_username = $config->get('vetrack_api')['username'];
		} 
		if ($config->has('vetrack_api') && isset($config->get('vetrack_api')['password'])) {
			$vetrack_password = $config->get('vetrack_api')['password'];
		} 
			
		if($vetrack_password == '' || $vetrack_username == '') {
			return;
		}			
			
		$data = $event->getData();
		$event = $event->getEvent();
		$form = $event->getForm();
	
		if($form->getName() === 'courses') {
			$sClie_Surname = $data['last'];
			$sClie_Given = $data['first'];
			$email = $data['email'];
			$phone = $data['phone'];
			
			$course = $data['courseselect'];
			$event_name = $data['eventname'];
			
			$dob = str_replace('/', '-', $data['dob']);
			$xsdClie_DOB = !is_bool($dob) ? Date('Y-m-d', strtotime($dob)) : '1970-01-01';

			$mobile_number = $data['phone'];
			$citizenship = $data['status'];

			$file = fopen("test.txt","w");	
			//fwrite($file, $vetrack_username);
					
			if(strlen($sClie_Given) >= 2 && strlen($sClie_Surname) >= 2 && (strlen($phone) >= 10 && strlen($phone) <= 12)) {
				
				$VETAPIUrl = "https://trainerportal.org.au/VETtrakAPI/VT_API.asmx?wsdl";
				$Client = new \SoapClient($VETAPIUrl);
				$Client->TAuthenticate = $Client->API_Handshake();

				// Validate client and Get Token
				$Credentials = new \stdClass; 
				$Credentials->sUsername = $vetrack_username;
				$Credentials->sPassword = $vetrack_password;
				$Client->TAuthenticate = $Client->ValidateClient($Credentials);

				// Add student to Vettrak Database
				$GetTokenObject = new \stdClass;               
				$GetTokenObject->sToken = $Client->TAuthenticate->ValidateClientResult->Token;
				$GetTokenObject->sClie_Surname = $sClie_Surname;
				$GetTokenObject->sClie_Given = $sClie_Given;
				$GetTokenObject->email = $email;
				$GetTokenObject->xsdClie_DOB = $xsdClie_DOB; //'2021-11-28T16:30:09.000';
				$GetTokenObject->divisionId = 0;

				$Client->TAuthClie = $Client->AddClientAfterCheck($GetTokenObject);

				$ReturnGiven = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Given;
				$ReturnSurname = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Surname;
				$ReturnCode = $Client->TAuthClie->AddClientAfterCheckResult->Clie->Clie_Code;
				$ReturnStatus = $Client->TAuthClie->AddClientAfterCheckResult->Auth->StatusMessage;	
				
				fwrite($file, 'valid'. $ReturnGiven . '/' . $ReturnCode . '/' . $ReturnStatus . '\n');				
				
				/// Add some additional data into Vetrack Student 
				$additionalFieldData = '<AdditionalData>
							<MobilePhone>'.$mobile_number.'</MobilePhone>
							<Citizenship>'.$citizenship.'</Citizenship>
						</AdditionalData>';
						
				$paramsObj = new \stdClass; 
				$paramsObj->token = $Client->TAuthenticate->ValidateClientResult->Token;
				$paramsObj->clientCode = $ReturnCode;
				$paramsObj->additionalFieldData = $additionalFieldData;
										
				$ReturnUpdate = $Client->UpdateClientAdditionalFields($paramsObj);

				fwrite($file, json_encode($ReturnUpdate->UpdateClientAdditionalFieldsResult->Status) . '\n');
				if(isset($ReturnUpdate->UpdateClientAdditionalFieldsResult->StatusMessage)) {
					fwrite($file, $ReturnUpdate->UpdateClientAdditionalFieldsResult->StatusMessage);
				}				
				
				/// Add Client Event
				date_default_timezone_set('Australia/Melbourne');
				$todaydate = date('Y-m-d', time());
				$todaydate = $todaydate."T00:00:00";               

				$EventsDes = new \stdClass;               
				$EventsDes->EventID = 0;
				$EventsDes->EventType = 0;
				$EventsDes->Identifier = $ReturnCode;
				$EventsDes->EventName = "EOI - " . htmlspecialchars_decode($event_name);
				$EventsDes->EventStart = $todaydate;
				$EventsDes->Complete = 0;
				$EventsDes->Description = $course;

				$AddEvents = new \stdClass;              
				$AddEvents->token = $Client->TAuthenticate->ValidateClientResult->Token;
				$AddEvents->eEvent = $EventsDes;               
				$Client->TAuthID = $Client->AddClientEvent($AddEvents);
				$Auth = $Client->TAuthID->AddClientEventResult->Auth;				
				
				$data['studentid'] = $ReturnCode;
				$event->setData($data);	
				
				fwrite($file, 'EventName = ' . $event_name);
				fwrite($file, 'AuthID = ' . $Auth->ID);
				fwrite($file, 'AuthID = ' . json_encode($Auth));
			} else {
				fwrite($file, 'invalid');
			}
			fclose($file);
		}
				
	}
	
    /**
     * Ran automatically, if the current request is from the command line (CLI).
     * You can use this method to set up things in your extension.
     *
     * Note: This runs on every request. Make sure what happens here is quick
     * and efficient.
     */
    public function initializeCli(): void
    {
    }
}
