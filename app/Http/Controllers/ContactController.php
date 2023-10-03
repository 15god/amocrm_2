<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Collections\CustomFields\CustomFieldEnumsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\Leads\LeadsCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Collections\TasksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Exceptions\AmoCRMApiNoContentException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\Customers\CustomerModel;
use AmoCRM\Models\CustomFields\EnumModel;
use AmoCRM\Models\CustomFields\NumericCustomFieldModel;
use AmoCRM\Models\CustomFields\SelectCustomFieldModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Models\TaskModel;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\OAuth2\Client\Token\AccessTokenInterface;

include_once base_path('vendor/amocrm/amocrm-api-library/examples/token_actions.php');
include_once base_path('vendor/amocrm/amocrm-api-library/examples/error_printer.php');

class ContactController extends Controller
{
    public function store(Request $request)
    {
        //validate input

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|digits:11',
            'age' => 'required|digits_between:1,3',
            'gender' => 'required|string|max:1',
        ]);

        //update token

        $apiClient = new AmoCRMApiClient(env('CLIENT_ID'), env('CLIENT_SECRET'), env('CLIENT_REDIRECT_URI'));

        $accessToken = getToken();

        $apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain($accessToken->getValues()['baseDomain'])
            ->onAccessTokenRefresh(
                function (AccessTokenInterface $accessToken, string $baseDomain) {
                    saveToken(
                        [
                            'accessToken' => $accessToken->getToken(),
                            'refreshToken' => $accessToken->getRefreshToken(),
                            'expires' => $accessToken->getExpires(),
                            'baseDomain' => $baseDomain,
                        ]
                    );
                }
            );

        //set up missing custom fields
        $customFieldsService = $apiClient->customFields(EntityTypesInterface::CONTACTS);

        if (empty($customFieldsService->get()->getBy('code', 'AGE'))) {

            $cf = new NumericCustomFieldModel();
            $cf->setName('Возраст');
            $cf->setCode('AGE');

            $customFieldsService->addOne($cf);
        };

        if (empty($customFieldsService->get()->getBy('code', 'GENDER'))) {

            $cf = new SelectCustomFieldModel();
            $cf->setName('Пол');
            $cf->setCode('GENDER');

            $enums = new CustomFieldEnumsCollection();

            $first = new EnumModel();
            $first->setCode('M');
            $first->setValue('Мужской');
            $first->setSort(1);
            $enums->add($first);

            $second = clone $first;
            $second->setCode('F');
            $second->setValue('Женский');
            $second->setSort(1);
            $enums->add($second);

            $cf->setEnums($enums);

            $customFieldsService->addOne($cf);
        };

        $catalog = $apiClient->catalogs()->get()->getBy('name', 'Товары');

        try{
            $catalogElementsCollection = $apiClient->catalogElements($catalog->getId())->get();
        } catch (AmoCRMApiNoContentException $e) {
            $catalogElementsCollection = new CatalogElementsCollection();
        }

        //add products if missing
        if (!($catalogElementsCollection->getBy('name', 'Товар1'))) {

            $catalogElementsCollection = new CatalogElementsCollection();
            $catalogElement1 = new CatalogElementModel();
            $catalogElement1->setName('Товар1')->setCustomFieldsValues(
                (new CustomFieldsValuesCollection())->add(
                    (new NumericCustomFieldValuesModel())->setFieldCode('PRICE')->setValues(
                        (new NumericCustomFieldValueCollection())
                            ->add(
                                (new NumericCustomFieldValueModel())
                                    ->setValue(10000)
                            )
                    )
                )
            );
            $catalogElementsCollection->add($catalogElement1);

            $catalogElementsService = $apiClient->catalogElements($catalog->getId());
            try {
                $catalogElementsService->add($catalogElementsCollection);
            } catch (AmoCRMApiException $e) {
                printError($e);
                die;
            }

        }

        if (!($catalogElementsCollection->getBy('name', 'Товар2'))) {

            $catalogElementsCollection = new CatalogElementsCollection();
            $catalogElement2 = new CatalogElementModel();
            $catalogElement2->setName('Товар2')->setCustomFieldsValues(
                (new CustomFieldsValuesCollection())->add(
                    (new NumericCustomFieldValuesModel())->setFieldCode('PRICE')->setValues(
                        (new NumericCustomFieldValueCollection())
                            ->add(
                                (new NumericCustomFieldValueModel())
                                    ->setValue(25000)
                            )
                    )
                )
            );
            $catalogElementsCollection->add($catalogElement2);

            $catalogElementsService = $apiClient->catalogElements($catalog->getId());
            try {
                $catalogElementsService->add($catalogElementsCollection);
            } catch (AmoCRMApiException $e) {
                printError($e);
                die;
            }


        }

        //find duplicates

        try{
            $allContacts = $apiClient->contacts()->get()->toArray();
        } catch (AmoCRMApiNoContentException $e) {
            $allContacts = new ContactsCollection();
            $allContacts = $allContacts->toArray();
        }

        foreach ($allContacts as $contact){
            if ( $contact['custom_fields_values'][0]['values'][0]['value'] == $request->phone){
                $contact_id = $contact['id'];
                break;
            }
        }

        if(isset($contact_id)){
            //check of it has a lead
            $leadLink = $apiClient->contacts()->getLinks($apiClient->contacts()->get()->getBy('id', $contact_id));
            if(!$leadLink->isEmpty()){
                $lead = $apiClient->leads()->get()->getBy('id',$leadLink->toArray()[0]['to_entity_id']);
                $contactModel = $apiClient->contacts()->get()->getBy('id',$contact_id);
                $contactLinks = $apiClient->contacts()->getLinks($contactModel)->toArray();

                foreach($contactLinks as $link){
                    if ($link['to_entity_type'] == "customers"){
                        $hasCustomer = true;
                        break;
                    }
                }
                //check lead status
                if($lead->getStatusId() == 142 && !($hasCustomer ?? false)){

                    $customersService = $apiClient->customers();
                    $contactModelArr = $contactModel->toArray();
                    $customer = new CustomerModel();
                    $customer->setName($contactModelArr['name']);

                    try {
                        $customer = $customersService->addOne($customer);
                    } catch (AmoCRMApiException $e) {
                        printError($e);
                        die;
                    }
                    //Link to contact
                    $links = new LinksCollection();
                    $links->add($contactModel);
                    try {
                        $customersService->link($customer, $links);
                    } catch (AmoCRMApiException $e) {
                        printError($e);
                        die;
                    }
                    return response('Customer created',200);
                }
                else{
                    return response('Lead is in progress or a customer is already exists',200);
                }
            }
            else{
                //skip making contact and make a lead
                $contactModel = $apiClient->contacts()->get()->getBy('id',$contact_id);
            }
        }


        //add contact
        if(!isset($contactModel)) {

            $contact = new ContactModel();

            $contact->setFirstName($request->first_name);
            $contact->setLastName($request->last_name);

            $customValues = new CustomFieldsValuesCollection();
            //dd($customFieldsService->get());

            //add Phone cf
            $customValues->add(
                (new MultitextCustomFieldValuesModel())->setFieldCode('PHONE')
                    ->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add(
                                (new MultitextCustomFieldValueModel())
                                    ->setValue($request->phone)
                            ))
            );

            //add Email cf
            $customValues->add(
                (new MultitextCustomFieldValuesModel())->setFieldCode('EMAIL')
                    ->setValues(
                        (new MultitextCustomFieldValueCollection())
                            ->add(
                                (new MultitextCustomFieldValueModel())
                                    ->setValue($request->email)
                            ))
            );

            //add Age cf
            $customValues->add(
                (new NumericCustomFieldValuesModel())->setFieldCode('AGE')
                    ->setValues(
                        (new NumericCustomFieldValueCollection())
                            ->add(
                                (new NumericCustomFieldValueModel())
                                    ->setValue($request->age)
                            ))
            );

            //add Gender cf
            $customValues->add(
                (new SelectCustomFieldValuesModel())->setFieldCode('GENDER')
                    ->setValues(
                        (new SelectCustomFieldValueCollection())
                            ->add(
                                (new SelectCustomFieldValueModel())
                                    ->setEnumCode(ucfirst($request->gender))
                            ))
            );

            $contact->setCustomFieldsValues($customValues);

            try {
                $contactModel = $apiClient->contacts()->addOne($contact);
            } catch (AmoCRMApiException $e) {
                printError($e);
                die;
            }
        }

        //add lead

        $leadsService = $apiClient->leads();

        $usersCollection = $apiClient->users()->get();
        $responsibleUser = $usersCollection[rand(0, $usersCollection->count() - 1)]->getId();

        $lead = new LeadModel();
        $lead->setName('Сделка c ' . $request->first_name . ' ' . $request->last_name)
            ->setPrice(rand(100000, 150000))
            ->setResponsibleUserId($responsibleUser)
            ->setContacts(
                (new ContactsCollection())
                    ->add($contactModel->
                    setIsMain(true))
            );

        $leadsCollection = new LeadsCollection();
        $leadsCollection->add($lead);

        //save lead
        try {
            $leadsCollection = $leadsService->add($leadsCollection);
        } catch (AmoCRMApiException $e) {
            printError($e);
            die;
        }

        //set time viable time for task
        $date = time() + 24 * 60 * 60 * 4;
        $taskTimeHrs = date('H', $date);
        if ($taskTimeHrs <= 9) {
            $date += (9 - $taskTimeHrs) * 60;
        } elseif ($taskTimeHrs >= 18) {
            $date += (24 - $taskTimeHrs + 9) * 60;
        }

        //add Task
        $tasksCollection = new TasksCollection();
        $task = new TaskModel();
        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
            ->setText('Новая задача')
            ->setCompleteTill($date)
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setResponsibleUserId($responsibleUser);
        $tasksCollection->add($task);

        $tasksService = $apiClient->tasks();
        try {
            $tasksCollection = $tasksService->add($tasksCollection);
        } catch (AmoCRMApiException $e) {
            printError($e);
            die;
        }
        //link products
        $linksCollection = new LinksCollection();
        $catalogElements = $apiClient->catalogElements($catalog->getId())->get();
        $linksCollection->add(
            $catalogElements->getBy('name', "Товар1")
                ->setQuantity(rand(1, 5)))
            ->add(
                $catalogElements->getBy('name', "Товар2")
                    ->setQuantity(rand(1, 5))
            );
        try {
            $linksCollection = $apiClient->leads()->link($lead, $linksCollection);
        } catch (AmoCRMApiException $e) {
            printError($e);
            die;
        }

        return response('Contact and Lead are created',200);

    }

    public function create(): View
    {

        return view('contacts-create');
    }

    public function tokenInit(): void
    {

        $apiClient = new AmoCRMApiClient(env('CLIENT_ID'), env('CLIENT_SECRET'), env('CLIENT_REDIRECT_URI'));

        session_start();


        if (isset($_GET['referer'])) {
            $apiClient->setAccountBaseDomain($_GET['referer']);
        }


        if (!isset($_GET['code'])) {
            $state = bin2hex(random_bytes(16));
            $_SESSION['oauth2state'] = $state;
            if (isset($_GET['button'])) {
                echo $apiClient->getOAuthClient()->getOAuthButton(
                    [
                        'title' => 'Установить интеграцию',
                        'compact' => true,
                        'class_name' => 'className',
                        'color' => 'default',
                        'error_callback' => 'handleOauthError',
                        'state' => $state,
                    ]
                );
                die;
            } else {
                $authorizationUrl = $apiClient->getOAuthClient()->getAuthorizeUrl([
                    'state' => $state,
                    'mode' => 'post_message',
                ]);
                header('Location: ' . $authorizationUrl);
                die;
            }
        } elseif (!isset($_GET['from_widget']) && (empty($_GET['state']) || empty($_SESSION['oauth2state']) || ($_GET['state'] !== $_SESSION['oauth2state']))) {
            unset($_SESSION['oauth2state']);
            exit('Invalid state');
        }

        /**
         * Ловим обратный код
         */
        try {
            $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($_GET['code']);

            if (!$accessToken->hasExpired()) {
                saveToken([
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $apiClient->getAccountBaseDomain(),
                ]);
            }
        } catch (Exception $e) {
            die((string)$e);
        }

        $ownerDetails = $apiClient->getOAuthClient()->getResourceOwner($accessToken);

        printf('Hello, %s!', $ownerDetails->getName());
    }
}
