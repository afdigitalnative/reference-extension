<?php

declare(strict_types=1);

namespace AcmeCorptest\ReferenceExtension;

use Bolt\Extension\ExtensionController;
use Bolt\Storage\EntityManager;
use Bolt\Repository\ContentRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Bolt\Storage\Query;
use Bolt\Configuration\Content\ContentType;
use Bolt\BoltForms\Factory\EmailFactory;
use Bolt\Common\Str;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Tightenco\Collect\Support\Collection;

class Controller extends ExtensionController
{
	/*
    public function index($name = 'foo'): Response
    {
        $context = [
            'title' => 'AcmeCorptest Reference Extension',
            'name' => $name,
        ];

        return $this->render('@reference-extension/page.html.twig', $context);
    }
	*/
	
	private function getToken(): string

	{

		$config = $this->getConfig();

		

		if ($config->has('vetrack_api') && isset($config->get('vetrack_api')['username'])) {

			$vetrack_username = $config->get('vetrack_api')['username'];

		} 

		if ($config->has('vetrack_api') && isset($config->get('vetrack_api')['password'])) {

			$vetrack_password = $config->get('vetrack_api')['password'];

		}



		if($vetrack_password == '' || $vetrack_username == '') {

		

			return null;



		}



		$VETAPIUrl = "https://trainerportal.org.au/VETtrakAPI/VT_API.asmx?wsdl";

		 

		$this->Client = new \SoapClient($VETAPIUrl);

		$this->Client->TAuthenticate = $this->Client->API_Handshake();



		// Validate client and Get Token

		$Credentials = new \stdClass; 

		$Credentials->sUsername = $vetrack_username;

		$Credentials->sPassword = $vetrack_password ;

		$this->Client->TAuthenticate = $this->Client->ValidateClient($Credentials);

		

		return $this->Client->TAuthenticate->ValidateClientResult->Token;

	}

	

    public function index(): Response

    {	

		$token = $this->getToken();

		

		if($token == null) {

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Vetrack API Credential not provided')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;

		}	

		

		// Get All Programmes/Courses

		$params = new \stdClass;

		$params->sToken = $token;

		$params->sPrgt_Name = 'Short Course';

		$params->xsdStart = (new \DateTime('first day of -2 month'))->format('c');

		$params->xsdEnd = (new \DateTime('last day of +3 month'))->format('c');



		$short_course_result = $this->Client->GetWebProgrammesForProgTypeAndDates($params);

		$result_programmes = $short_course_result->GetWebProgrammesForProgTypeAndDatesResult;

		$prog_list = $result_programmes->ProgList;

		//$course_list = array();

		$course_desc = '';

		$exist_course = false;

		

		if(isset($result_programmes->ProgList) && isset($result_programmes->ProgList->TProg)) {

							

			if(is_array($result_programmes->ProgList->TProg)) {

				

				foreach($result_programmes->ProgList->TProg as $prog_item) {

					/*

					array_push($course_list, array(

												'id' => $prog_item->Prog_ID,

												'prog_name' => $prog_item->Prog_Name,

												'prog_code' => $prog_item->Prog_Code,

												'prog_desc' => $prog_item->Prog_Desc,

											));

					*/

					if((int)$_POST['course_id'] == $prog_item->Prog_ID) {

						

						$course_desc = $prog_item->Prog_Desc;

						$exist_course = true;

						

					}

				}					



			} else {

				

					$prog_item = $result_programmes->ProgList->TProg;

					/*

					array_push($course_list, array(

												'id' => $prog_item->Prog_ID,

												'prog_name' => $prog_item->Prog_Name,

												'prog_code' => $prog_item->Prog_Code,

												'prog_desc' => $prog_item->Prog_Desc,

											));

					*/

					if((int)$_POST['course_id'] == $prog_item->Prog_ID) {

						

						$course_desc = $prog_item->Prog_Desc;

						$exist_course = true;

						

					}					

			}

			

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Invalid Course')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;

			

		}

		

		if(!$exist_course) {



			$response = new Response(json_encode(array('error' => true, 'msg' => 'The course is not available.')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;	

			

		}	

		

		

		// Get All Occurrences

		$params = new \stdClass;

		$params->sToken = $token;

		$params->iProg_ID = (int)$_POST['course_id'];

		$params->xsdStart = (new \DateTime('first day of -2 month'))->format('c');

		$params->xsdEnd = (new \DateTime('last day of +3 month'))->format('c');



		$result_occurs = $this->Client->GetWebOccurrencesForProgrammeAndDates($params);

		$occurs = $result_occurs->GetWebOccurrencesForProgrammeAndDatesResult;



		$occurs_list = array();



		if(isset($occurs->OccuList)) {

			

			if(isset($occurs->OccuList->TOccu)) {

				

				if(is_array($occurs->OccuList->TOccu)) {

					

					foreach($occurs->OccuList->TOccu as $occur_item) {

						array_push($occurs_list, 

											array(

												'id' => $occur_item->ID,

												'code' => $occur_item->Code,

												//'start_date' => $occur_item->StartDate,

												'end_date' => $occur_item->EndDate,

												'loca_code' => $occur_item->Loca_Code,

												'delivery_modes' => isset($occur_item->DeliveryModes) ? $occur_item->DeliveryModes->string : '',

												'amount' => $occur_item->Amount,

												'amount_paid' => $occur_item->AmountPaid,

												'amount_credited' => $occur_item->AmountCredited,

												'prog_code' => $occur_item->Prog_Code,

												'prog_name' => $occur_item->Prog_Name,

												'vacancies' => $occur_item->Vacancies,

												'color' => $occur_item->Colour,

											)

						);

					}

				

				} else {

					

					$occur_item = $occurs->OccuList->TOccu;

					array_push($occurs_list, 

										array(

											'id' => $occur_item->ID,

											'code' => $occur_item->Code,

											//'start_date' => $occur_item->StartDate,

											'end_date' => $occur_item->EndDate,

											'loca_code' => $occur_item->Loca_Code,

											'delivery_modes' => isset($occur_item->DeliveryModes) ? $occur_item->DeliveryModes->string : '',

											'amount' => $occur_item->Amount,

											'amount_paid' => $occur_item->AmountPaid,

											'amount_credited' => $occur_item->AmountCredited,

											'prog_code' => $occur_item->Prog_Code,

											'prog_name' => $occur_item->Prog_Name,

											'vacancies' => $occur_item->Vacancies,

											'color' => $occur_item->Colour,

										)

					);					

					

				}



			

				/*************************************

				

						Get Classes

						

				**************************************/

				

				foreach($occurs_list as &$occur) {

					

					$params = new \stdClass;

					$params->token = $token;

					$params->occurrenceId = (int)$occur['id'];



					$occur_detail_result = $this->Client->GetOccurrenceExtendedDetails($params);

					$occur_detail = $occur_detail_result->GetOccurrenceExtendedDetailsResult;



					if(isset($occur_detail->OccuExtended)) {

						

						$classes = [];	

						

						if(isset($occur_detail->OccuExtended->Classes)) {

							

							if(isset($occur_detail->OccuExtended->Classes->TInst)) {

							

								if(is_array($occur_detail->OccuExtended->Classes->TInst)) {

									

									foreach($occur_detail->OccuExtended->Classes->TInst as $class) {

										array_push($classes, 

															array(

																'id' => $class->Inst_ID,

																'start_date' => $class->Inst_Start,

																'end_date' => $class->Inst_Finish,

																'room_code' => isset($class->Room_Code) ? $class->Room_Code : '',

																'inst_code' => isset($class->Inst_Code) ? $class->Inst_Code : ''

															)

										);

									}

								

								} else {

									

									$class = $occur_detail->OccuExtended->Classes->TInst;

									array_push($classes, 

														array(

															'id' => $class->Inst_ID,

															'start_date' => $class->Inst_Start,

															'end_date' => $class->Inst_Finish,

															'room_code' => isset($class->Room_Code) ? $class->Room_Code : '',

															'inst_code' => isset($class->Inst_Code) ? $class->Inst_Code : ''

														)

									);				

									

								}

							

							

								$occur['classes'] = $classes;

								$occur['start_date'] = $classes[0]['start_date'];

								$occur['start_date_formatted'] = date('M d, Y', strtotime($classes[0]['start_date']));

								$occur['start_time'] = date('h:i A', strtotime($classes[0]['start_date']));

								$occur['end_time'] = date('h:i A', strtotime($classes[0]['end_date']));

								

							} else {

								

								$occur['classes'] = [];

								

								//$response = new Response(json_encode(array('error' => true, 'msg' => 'No occurrences available!')));

								//$response->headers->set('Content-Type', 'application/json');

			

								//return $response;								

								

							}

													

						} else {

							

							$occur['classes'] = [];

							

							//$response = new Response(json_encode(array('error' => true, 'msg' => 'No occurrences available!')));

							//$response->headers->set('Content-Type', 'application/json');

		

							//return $response;

						

						}

							

					} else {
						
						$occur['classes'] = [];
						

						//$response = new Response(json_encode(array('error' => true, 'msg' => 'No occurrences available!')));

						//$response->headers->set('Content-Type', 'application/json');

		

						//return $response;

						

					}					

						

				}

								

				

				// Get Location List

	

				$params = new \stdClass;

				$params->token = $token;

				$params->programmeId = $_POST['course_id'];

				$params->startDate = (new \DateTime('first day of -2 month'))->format('c');

				$params->endDate = (new \DateTime('last day of +3 month'))->format('c');



				$result_locations = $this->Client->GetWebLocationsForProgrammeAndDates($params);

				$locations= $result_locations->GetWebLocationsForProgrammeAndDatesResult;



				$location_list = array();



				if(isset($locations->LocaList)) {

					

					if(is_array($locations->LocaList->TLoca)) {

						

						foreach($locations->LocaList->TLoca as $location_item) {

							array_push($location_list, 

										array(

											'loca_code' => $location_item->Loca_Code,
																						
											'loca_address'	=> $location_item->Address,

											'state_shortname' => $location_item->Stat_ShortName

										)

							);

						}

					

					} else {

						

						$location_item = $locations->LocaList->TLoca;

						array_push($location_list, 

									array(

										'loca_code' => $location_item->Loca_Code,

										'loca_address'	=> $location_item->Address,

										'state_shortname' => $location_item->Stat_ShortName

									)

						);						

						

					}



				}			

			

				

				

				$data = array('course_desc' => strip_tags($course_desc), 'occurs' => $occurs_list, 'locs' => $location_list);

				$response = new Response(json_encode(array('error' => false, 'data' => $data)));

				$response->headers->set('Content-Type', 'application/json');



				return $response;				

				

			} else {

				

				$response = new Response(json_encode(array('error' => true, 'msg' => $occurs->Auth->StatusMessage)));

				

			}

			

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => $occurs->Auth->StatusMessage)));

			

		}

	

		

		////////////////////

			

		/*

		$occurs = json_decode(file_get_contents("occurs.json"), true);

		$locs = json_decode(file_get_contents("locs.json"), true);



		$data = array('occurs' => $occurs, 'locs' => $locs);

		

		$response = new Response(json_encode(array('error' => false, 'data' => $data)));

		*/

		// "45146"

		

		//////////////////////

		

		$response->headers->set('Content-Type', 'application/json');

		

		return $response;

		

    }

	

    public function getOccurExtendedDetail(): Response

    {

		$token = $this->getToken(); 

		if($token == null) {

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Vetrack API Credential not provided')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;

		}		



		$params = new \stdClass;

		$params->token = $token;

		$params->occurrenceId = (int)$_POST['occur_id'];



		$occur_detail_result = $this->Client->GetOccurrenceExtendedDetails($params);

		$occur_detail = $occur_detail_result->GetOccurrenceExtendedDetailsResult;



		if(isset($occur_detail->OccuExtended)) {

			$start_date_formatted = date('M d, Y', strtotime($occur_detail->OccuExtended->OccurrenceDetail->StartDate));

			$end_date_formatted = date('M d, Y', strtotime($occur_detail->OccuExtended->OccurrenceDetail->EndDate));

			$start_time = date('h:i A', strtotime($occur_detail->OccuExtended->OccurrenceDetail->EndDate));



			$occur_ext_detail =	array(

								'occur_id' => (int)$_POST['occur_id'],

								'code' => $occur_detail->OccuExtended->OccurrenceDetail->Code,

								'start_date' => $occur_detail->OccuExtended->OccurrenceDetail->StartDate,

								'end_date' => $occur_detail->OccuExtended->OccurrenceDetail->EndDate,

								'start_date_formatted' => $start_date_formatted,

								'end_date_formatted' => $end_date_formatted,

								'loca_code' => $occur_detail->OccuExtended->OccurrenceDetail->Loca_Code,

								'delivery_modes' => isset($occur_detail->OccuExtended->OccurrenceDetail->DeliveryModes) ? $occur_detail->OccuExtended->OccurrenceDetail->DeliveryModes->string : '',

								'amount' => $occur_detail->OccuExtended->OccurrenceDetail->Amount,

								'prog_name' => $occur_detail->OccuExtended->OccurrenceDetail->Prog_Name,

								'prog_code' => $occur_detail->OccuExtended->OccurrenceDetail->Prog_Code,						

							);

			

			$classes = [];	

			

			if(isset($occur_detail->OccuExtended->Classes)) {

				

				if(is_array($occur_detail->OccuExtended->Classes->TInst)) {

					

					foreach($occur_detail->OccuExtended->Classes->TInst as $class) {

						array_push($classes, 

											array(

												'id' => $class->Inst_ID,

												'start_date' => $class->Inst_Start,

												'end_date' => $class->Inst_Finish,

												'room_code' => $class->Room_Code,

												'inst_code' => $class->Inst_Code

											)

						);

					}

				

				} else {

					

					$class = $occur_detail->OccuExtended->Classes->TInst;

					array_push($classes, 

										array(

											'id' => $class->Inst_ID,

											'start_date' => $class->Inst_Start,

											'end_date' => $class->Inst_Finish,

											'room_code' => $class->Room_Code,

											'inst_code' => $class->Inst_Code

										)

					);				

					

				}

			

			

				$occur_ext_detail['classes'] = $classes;

				$occur_ext_detail['start_date'] = $classes[0]['start_date'];

				$occur_ext_detail['start_date_formatted'] = date('M d, Y', strtotime($classes[0]['start_date']));

				$occur_ext_detail['start_time'] = date('h:i A', strtotime($classes[0]['start_date']));

				$occur_ext_detail['end_time'] = date('h:i A', strtotime($classes[0]['end_date']));

				

				$response = new Response(json_encode(array('error' => false, 'data' => $occur_ext_detail)));

			

			} else {

				

				$response = new Response(json_encode(array('error' => true, 'msg' => 'There is no available classes.')));

				

			}

				

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Not occurrrence detail')));

			

		}

		

		

		////////////////////////////

		

		/*

		$test = json_decode(file_get_contents("occur_ext.txt"), true);

		$response = new Response(json_encode(array('error' => false, 'data' => $test)));

		*/

		

		/////////////////////////////

		

		$response->headers->set('Content-Type', 'application/json');

		

		return $response;

		

    }

	

    public function getStudentDetail($name = 'foo'): Response

    {

		$token = $this->getToken();

		if($token == null) {

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Vetrack API Credential not provided')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;

		}

		

		$params = new \stdClass;

		$params->sToken = $token;

		$params->sClie_Code = $_POST['student_id'];



		$student_detail_result = $this->Client->GetClientDetails($params);



		if(isset($student_detail_result->GetClientDetailsResult->ClieDetail)) {

			$student_detail = $student_detail_result->GetClientDetailsResult->ClieDetail;

			

			$address_unit = isset($student_detail->UsualAddress->UnitDetails) ? $student_detail->UsualAddress->UnitDetails : '';

			$address_building = isset($student_detail->UsualAddress->Building) ? $student_detail->UsualAddress->Building : '';

			$address_street_number = isset($student_detail->UsualAddress->StreetNumber) ? $student_detail->UsualAddress->StreetNumber : '';

			$address_street_name = isset($student_detail->UsualAddress->StreetName) ? $student_detail->UsualAddress->StreetName : '';

			

			//$dob_formatted = date('d/m/Y', strtotime($student_detail->Clie_DOB));

			

			if(isset($student_detail->Clie_DOB) && $student_detail->Clie_DOB != null) {

				

				$dob_formatted = date('d/m/Y', strtotime($student_detail->Clie_DOB));

				

				if($dob_formatted == $_POST['dob']) {

				

					$data = array(

						'first_name' => $student_detail->Clie_Given,

						'last_name' => $student_detail->Clie_Surname,

						'code' => $_POST['student_id'],

						'dob' => $student_detail->Clie_DOB,

						'dob_formatted' => date('d/m/Y', strtotime($student_detail->Clie_DOB)),

						'email' => $student_detail->Clie_Email,

						'phone' => $student_detail->Clie_MobilePhone,

						'address_unit' => $address_unit,

						'address_building' => $address_building,

						'address_street_number' => $address_street_number,

						'address_street_name' => $address_street_name,

						'city' => $student_detail->UsualAddress->City,

						'state' => isset($student_detail->UsualAddress->State) ? $student_detail->UsualAddress->State : '',

						'postcode' => $student_detail->UsualAddress->Postcode

					);

					

					$response = new Response(json_encode(array('error' => false, 'data' => $data)));



					

				} else {

					

					$response = new Response(json_encode(array('error' => true, 'msg' => 'Date of birth does not match. Please try to input the correct date of birth.')));

					

				}

				

			} else {



				$response = new Response(json_encode(array('error' => true, 'msg' => 'There is not a DOB value stored in database.')));

				

			}

						

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => $student_detail_result->GetClientDetailsResult->Auth->StatusMessage)));

		

		}	



		return $response;

	}



    public function registerStudent($name = 'foo'): Response

    {

		$token = $this->getToken();

		if($token == null) {

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Vetrack API Credential not provided')));

			$response->headers->set('Content-Type', 'application/json');



			return $response;

		}

		

		// Add student to Vettrak Database

		$params = new \stdClass;               

		$params->sToken = $token;

		$params->sClie_Surname = $_POST['last_name'];

		$params->sClie_Given = $_POST['first_name'];

		$params->email = $_POST['email'];

		$dob = str_replace('/', '-', $_POST['dob_formatted']);

		$params->xsdClie_DOB = Date('Y-m-d', strtotime($dob)); //'2021-11-28T16:30:09.000';

		$params->divisionId = 0;



		$this->Client->TAuthClie = $this->Client->AddClientAfterCheck($params);

		$student = $this->Client->TAuthClie->AddClientAfterCheckResult;



		if(isset($student->Clie)) {

			

			$data = array(

				'code' => $student->Clie->Clie_Code,

			);

			

			$company = isset($_POST['company']) ? $_POST['company'] : '';

			

			/// Add some additional data into Vetrack Student 

			$additionalFieldData = '<AdditionalData>

						<MobilePhone>'.$_POST['phone'].'</MobilePhone>

						<UsualAddressUnitDetails>'.$_POST['address_unit'].'</UsualAddressUnitDetails>

						<UsualAddressBuilding>'.$_POST['address_building'].'</UsualAddressBuilding>

						<UsualAddressStreetNumber>'.$_POST['address_street_number'].'</UsualAddressStreetNumber>

						<UsualAddressStreetName>'.$_POST['address_street_name'].'</UsualAddressStreetName>

						<UsualAddressCity>'.$_POST['city'].'</UsualAddressCity>

						<UsualAddressStateCode>'.$_POST['state'].'</UsualAddressStateCode>

						<UsualAddressPostcode>'.$_POST['postcode'].'</UsualAddressPostcode>

						<UsualAddressNotSpecified>N</UsualAddressNotSpecified>

					</AdditionalData>';

					

			$paramsObj = new \stdClass; 

			$paramsObj->token = $token;

			$paramsObj->clientCode = $student->Clie->Clie_Code;

			$paramsObj->additionalFieldData = $additionalFieldData;

									

			$ReturnUpdate = $this->Client->UpdateClientAdditionalFields($paramsObj);			

			

			if(isset($ReturnUpdate->UpdateClientAdditionalFieldsResult->StatusMessage)) {

				

				$response = new Response(json_encode(array('error' => true, 'msg' => $ReturnUpdate->UpdateClientAdditionalFieldsResult->StatusMessage)));

				

			} else {

				

				$response = new Response(json_encode(array('error' => false, 'data' => $data)));

				

			}

			

			

			

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => $student->Auth->StatusMessage)));

			

		}	

		

		

		/*

		$to = $_POST['email'];

		$subject = "New student registration notifiction from VICSEG Website";



		$message = "

			<html>

			<head>

				<title>New member registration</title>

			</head>

			<body>

			<table style='border-collapse: collapse;'>

				<tr>

					<th style='border: 1px solid #aaa; background-color: #f2f2f2'>Name</th>

					<th style='border: 1px solid #aaa; background-color: #f2f2f2'>Email</th>

					<th style='border: 1px solid #aaa; background-color: #f2f2f2'>Student ID</th>

				</tr>

				<tr>

					<td style='border: 1px solid #aaa'>".$_POST['first_name'].' '.$_POST['last_name']."</td>

					<td style='border: 1px solid #aaa'>".$_POST['email']."</td>

					<td style='border: 1px solid #aaa'>".$data['code']."</td>

				</tr>

			</table>

			</body>

			</html>

		";

		

		$headers = "MIME-Version: 1.0" . "\r\n";

		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";



		$headers .= 'From: <vicsegnewfutures.org.au>' . "\r\n";

		$result = mail($to,$subject,$message,$headers);			

		*/

		

		//////////////

		

		/*

		$data = array(

			'code' => 45015,

		);

		$response = new Response(json_encode(array('error' => false, 'data' => $data)));



		*/

		

		//////////////

		

		$response->headers->set('Content-Type', 'application/json');

		

		return $response;

	}	

	

    public function proceedPayment(MailerInterface $mailer): Response

    {

		if( !isset($_POST['email']) || $_POST['email'] == '' ) {

			$response = new Response(json_encode(array('error' => true, 'msg' => 'The email address is not provided.')));

			$response->headers->set('Content-Type', 'application/json');

		

			return $response;			

		}

		

		

		require_once 'lib/eway-rapid-php-master/include_eway.php';



		/** Sandbox ***/

		/* 
		$apiKey = 'C3AB9CQ8vhYDFXBnH2Tsj8vQhJmzrNywgYHWTXFF2pOKZbLIEky96vCjketZJqb1Pnqyoz';
		$apiPassword = 'zOWJUq0s';
		$apiEndpoint = \Eway\Rapid\Client::MODE_SANDBOX;
		*/

	

		/*** Live ****/

		$apiKey = '44DD7AkcilbvXE9Qh/8HUYtVjzxoEdSRNb/CZaMIhFNoMv9mDWnQP20tuKqEiyVpmwoc8g';
		$apiPassword = 'KwV8m6Xj';
		$apiEndpoint = \Eway\Rapid\Client::MODE_PRODUCTION;		



		

		$client = \Eway\Rapid::createClient($apiKey, $apiPassword, $apiEndpoint);



		$transaction = [

			'Customer' => [

				'FirstName' => $_POST['first_name'],

				'LastName' => $_POST['last_name'],

				'Phone' => $_POST['phone'],

				'Email' => $_POST['email'],

				'Street1' => $_POST['address_street_number'],

				'Street2' => $_POST['address_street_name'],

				'City' => $_POST['city'],

				'State' => $_POST['state'],

				'PostalCode' => $_POST['postcode'],				

				'CardDetails' => [

					'Name' => $_POST['card_name'],

					'Number' => $_POST['card_number'],

					'ExpiryMonth' => $_POST['card_expire_month'],

					'ExpiryYear' => $_POST['card_expire_year'],

					'CVN' => $_POST['card_ccv'],

				]

			],

			'Payment' => [

				'TotalAmount' => floatval($_POST['amount']) * 100,

			],

			'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,

		];



		$api_response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

		

		

		if ($api_response->TransactionStatus) {

			

			$full_name = $_POST['first_name'] . ' ' . $_POST['last_name'];

			$dob = str_replace('/', '-', $_POST['dob_formatted']);

								

			$student_email_data = array(

									'name' => $full_name, 

									'course_name' => $_POST['course_name'],

									'course_date' => $_POST['course_date'],

									'course_start_time' => $_POST['course_start_time'],

									'course_end_time' => $_POST['course_end_time'],

									'phone' => $_POST['phone'],

									'email' => $_POST['email'],

									'street' => $_POST['address_street_name'],

									'city' => $_POST['city'],

									'state' => $_POST['state'],

									'postal' => $_POST['postcode'],

									'occur_id' => $_POST['occur_id'],

									'studentid' => $_POST['code'],

									'dob' => Date('Y-m-d', strtotime($dob)),

									'error_message' => ''

								);



			$admin_email_data = array(

									'course_name' => $_POST['course_name'],

									'course_date' => $_POST['course_date'],

									'first_name' => $_POST['first_name'],

									'last_name' => $_POST['last_name'],

									'phone' => $_POST['phone'],

									'email' => $_POST['email'],

									'street' => $_POST['address_street_name'],

									'city' => $_POST['city'],

									'state' => $_POST['state'],

									'postal' => $_POST['postcode'],

									'occur_id' => $_POST['occur_id'],

									'studentid' => $_POST['code'],

									'dob' => Date('Y-m-d', strtotime($dob)),

									'error_message' => ''

								);

								

								

			// Register Booking into Vetrack

			$token = $this->getToken();

			if($token == null) {

				$response = new Response(json_encode(array('error' => true, 'msg' => 'Vetrack API Credential not provided')));

				$response->headers->set('Content-Type', 'application/json');



				return $response;

			}

		

		

			$params = new \stdClass;

			$params->sToken = $token;	

			

			$params->iOccu_ID = (int)$_POST['occur_id'];

			$params->sSurname = $_POST['last_name'];

			$params->sGiven = $_POST['first_name'];

			$params->sCode = (int)$_POST['code'];

			$params->xsdDOB = Date('Y-m-d', strtotime($dob));

			$params->sDescription = 'Web Enrolment';

			$params->referralId = 0;



			$temp = $this->Client->AddClientWebEnrolment($params);

			$result = $temp->AddClientWebEnrolmentResult;			

			

			if(isset($result->Auth->Status) && $result->Auth->Status == 1) {

				

				$data = array(

								'transaction_id' => $api_response->TransactionID, 

								'name' => $full_name, 

								'email' => $_POST['email'],

								'booking_failed' => false,

								'booking_status' => $result->Auth->StatusMessage,

								'invoice_failed' => false

							);

				

				////////////////// Add Web Payment ///////////////



				$params = new \stdClass;

				$params->sToken = $token;

				$params->iWebe_ID  = $result->ID;

				$params->sOrder  = '';

				$params->sRRN  = $api_response->TransactionID;

				$params->cAmountPaid = floatval($_POST['amount']);



				$add_web_payment = $this->Client->AddWebPayment($params);

				$add_web_payment_result = $add_web_payment->AddWebPaymentResult;

				

				if($add_web_payment_result->Status < 0) {

					

					$data['invoice_failed'] = true;

					$data['invoice_status'] = $add_web_payment_result->StatusMessage;

					

					$admin_email_data['error_message'] = 'The payment was successful and your booking is registered, but the invoice creation has failed. Please call us on 03 9093 5166 : ' . $add_web_payment_result->StatusMessage;

					$student_email_data['error_message'] = 'The payment was successful and your booking is registered, but the invoice creation has failed. Please call us on 03 9093 5166 : ' . $add_web_payment_result->StatusMessage;



				}				

				

			} else {

				

				$data = array(

								'transaction_id' => $api_response->TransactionID, 

								'name' => $full_name, 

								'email' => $_POST['email'],

								'booking_failed' => true,

								'booking_status' => $result->Auth->StatusMessage

							);				

				

				$admin_email_data['error_message'] = 'The payment was successful, but an error occurred while registering booking. [' . $result->Auth->StatusMessage . '] Please call us on 03 9093 5166';

				$student_email_data['error_message'] = 'The payment was successful, but an error occurred while registering booking. [' . $result->Auth->StatusMessage . '] Please call us on 03 9093 5166';



			}			

			

			

			// Automatic Confirmation Email after Successful Payment (To Student)

			$email = (new TemplatedEmail())

				->from($this->getFrom())

				->to($_POST['email'])

				->subject('Your booking with VICSEG New Futures')

				->htmlTemplate('@theme/forms/confirm_payment_email.html.twig') //('@boltforms/email.html.twig') 

				->context([

					'data' => $student_email_data,

					'formname' => 'test form',

					'meta' => array('url' => 'test'),

				]);			

		



			$mailer->send($email);			

			

			// Admin Notification Email

			$admin_email = (new TemplatedEmail())

				->from($this->getFrom())

				->to('shortcourses@vicsegnewfutures.org.au') // shortcourses@vicsegnewfutures.org.au

				->subject('Your booking with VICSEG New Futures')

				->htmlTemplate('@theme/forms/confirm_payment_admin_email.html.twig') //('@boltforms/email.html.twig') 

				->context([

					'data' => $admin_email_data,

					'formname' => 'test form',

					'meta' => array('url' => 'test'),

				]);			

		



			$mailer->send($admin_email);			

			

			

			$response = new Response(json_encode(array('error' => false, 'data' => $data)));	

			

		} else if($api_response->Errors) {

			

			$errors = explode(',', $api_response->Errors);

			$eway_errors = json_decode(file_get_contents("eway_errors.json"), true);

			$error_messages = [];



			foreach($errors as $error) {

				foreach($eway_errors as $item) {

					if($item['ResponseMessage'] == $error) {

						array_push($error_messages, $item['Reason']);

						

						break;

					}

				}

			}

			

			$response = new Response(json_encode(array('error' => true, 'eway_error' => true, 'msg' => $error_messages)));

			

		} else {

			

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Unexpected error occurred')));

			

		}			

		

		

		

		$response->headers->set('Content-Type', 'application/json');

		

		return $response;



	}



    public function applyPromo(Query $query): Response

    {

		$promos_interate = $query->getContent('discounts', array('code' => '%'.$_POST['promo_code'].'%'));

		$promos_interate->setMaxPerPage(1000);			

		$promos = iterator_to_array($promos_interate->getCurrentPageResults());

		$promo_match = array();

		

		foreach($promos as $promo) {

			if($promo->getFieldValue('code') == $_POST['promo_code']) {

				$promo_match = array(

								'title' => $promo->getFieldValue('title'),

								'code' => $promo->getFieldValue('code'),

								'amount' => $promo->getFieldValue('value'),

							);

				break;

			}

		}

		

		if(count($promo_match) > 0) {

			

			$response = new Response(json_encode(array('error' => false, 'data' => $promo_match )));

		

		} else {

		

			$response = new Response(json_encode(array('error' => true, 'msg' => 'Invalid promo code' )));

		

		}

			

		$response->headers->set('Content-Type', 'application/json');

		

		return $response;

	}

	

    protected function getFrom(): Address

    {

        return $this->getAddress('vicseg@simplecreatif.com', 'VICSEG New Futures Website');

    }



    private function getAddress(string $email, string $name): Address

    {

        return new Address($email, $name);

    }	
	
}
