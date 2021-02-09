<?php

namespace PullApi\Http\Controllers\Transfers;

use PullApi\Enums\TransactionStatuses;
use PullApi\Enums\UsingServiceTypes;
use PullApi\Http\Controllers\Controller;
use PullApi\Http\Controllers\Helpers\ApiResponse;
use PullApi\Http\Controllers\Helpers\MakeClientRequest;
use PullApi\Http\Requests\TransactionType\SelfService\CreateDepositRequest;
use PullApi\Http\Requests\TransactionType\SelfService\CreatePickupRequest;
use Illuminate\Http\Response;
use PullApi\Services\TransfersService;

class SelfServiceController extends Controller
{
    private $transfersService;
    private $makeClientRequest;

    public function __construct
    (
        TransfersService $transfersService,
        MakeClientRequest $makeClientRequest
    )
    {
        $this->transfersService = $transfersService;
        $this->makeClientRequest = $makeClientRequest;

        parent::__construct();
    }

    // todo in future standard two methods  createPickup and createDeposit
    /**
     * Method to create deposit transaction
     * @param CreateDepositRequest $request
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function createDeposit(CreateDepositRequest $request)
    {
        $requestAdded = $this->requestAdditional($request->all());

        $data = [
            'receivingCountry' => $requestAdded['receivingCountry'],
            'currency' => $requestAdded['currency'],
            'country' => $requestAdded['receivingCountry'],
            'transferType' => isset($requestAdded['transferType']) ? $requestAdded['transferType'] : null,
            'is_deposit' => 1,
            'is_pickup' => 0,
            'amount' => $requestAdded['amount'],
            'user_id' => $request->user()->id
        ];

        $fee = $this->transfersService->getFeeForTransaction($data);

        if($fee == null) {
            $data['receivingCountry'] = $requestAdded['receivingCountry'];
            $data['amount'] = $requestAdded['amount'];
            $data['currency'] = $requestAdded['currency'];
            $data['transferType'] = isset($requestAdded['transferType']) ? $requestAdded['transferType'] : null;
            $data['username'] = $request->user()->username;
            $data['isWebsite'] = true;
            $remoteFeeResponse = $this->makeClientRequest->getFee($data);
            $remoteFeeResponseContent = (array) $remoteFeeResponse['content'];
            $description = "Wrong amount or No fee error!";
            $message = "Wrong amount or No fee is set for this destination!";
            if (isset($remoteFeeResponseContent['statusCode']) && $remoteFeeResponseContent['statusCode'] === Response::HTTP_OK) {
                $requestAdded['fee'] = $remoteFeeResponseContent['commission'];
            }else {
                if(isset($remoteFeeResponseContent['description'])) {
                    $description = $remoteFeeResponseContent['description'];
                }
                if($remoteFeeResponseContent['message']) {
                    $message = $remoteFeeResponseContent['message'];
                }
                return ApiResponse::respondWithError(
                    $description,
                    $message,
                    404
                );
            }
        }else {
            $requestAdded['fee'] = $fee->fee_in_percent !== null ? ($requestAdded['amount']*$fee->fee_in_percent)/100 : $fee->fee_fixed_amount;
        }

        $response = $this->makeClientRequest->createDepositRequest($requestAdded);

        $responseContent = $response['content'];

        if ($response['status'] === Response::HTTP_OK) {
            $this->proceedNewTransaction($responseContent, $requestAdded, $request->ip());
            $message = [
                'pid' => $responseContent->pid,
                'transactionStatus' => $responseContent->transactionStatus
            ];

            if ($responseContent->transactionIDExternal) {
                $message['transactionIDExternal'] = $responseContent->transactionIDExternal;
            }

            return ApiResponse::respond([
                "description" => $responseContent->message,
                "message" => $message
            ]);

        } else {
            if (isset($responseContent->description) && isset($responseContent->message) && isset($responseContent->statusCode)) {
                return ApiResponse::respondWithError(
                    $responseContent->description,
                    $responseContent->message ,
                    $responseContent->statusCode
                );
            } else {
                return ApiResponse::respondWithError();
            }
        }
    }

    /**
     * Method to create pickup transaction
     * @param CreatePickupRequest $request
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function createPickup(CreatePickupRequest $request)
    {
        $requestAll = $request->all();

        $data = [
            'receivingCountry' => $requestAll['receivingCountry'],
            'currency' => $requestAll['currency'],
            'country' => $requestAll['receivingCountry'],
            'transferType' => $requestAll['transferType'],
            'is_pickup' => 1,
            'is_deposit' => 0,
            'amount' => $requestAll['amount'],
            'user_id' => $request->user()->id
        ];

        $fee = $this->transfersService->getFeeForTransaction($data);

        if($fee == null) {
            $data['receivingCountry'] = $requestAll['receivingCountry'];
            $data['amount'] = $requestAll['amount'];
            $data['currency'] = $requestAll['currency'];
            $data['transferType'] = $requestAll['transferType'];
            $data['username'] = $request->user()->username;
            $data['isWebsite'] = true;
            $remoteFeeResponse = $this->makeClientRequest->getFee($data);
            $remoteFeeResponseContent = (array) $remoteFeeResponse['content'];
            $description = "Wrong amount or No fee error!";
            $message = "Wrong amount or No fee is set for this destination!";
            if ($remoteFeeResponseContent['statusCode'] === Response::HTTP_OK) {
                $requestAll['fee'] = $remoteFeeResponseContent['commission'];
            }else {
                if($remoteFeeResponseContent['description']) {
                    $description = $remoteFeeResponseContent['description'];
                }
                if($remoteFeeResponseContent['message']) {
                    $message = $remoteFeeResponseContent['message'];
                }
                return ApiResponse::respondWithError(
                    $description,
                    $message,
                    404
                );
            }
        }else {
            $requestAll['fee'] = $fee->fee_in_percent !== null ? ($requestAll['amount']*$fee->fee_in_percent)/100 : $fee->fee_fixed_amount;
        }


        $requestAdded = $this->requestAdditional($requestAll);
        $requestAdded['username'] = \Auth::user()->username;
        $response = $this->makeClientRequest->createPickupRequest($requestAdded);
        $responseContent = $response['content'];

        if ($response['status'] === Response::HTTP_OK) {
            $this->proceedNewTransaction($responseContent, $requestAdded, $request->ip());

            $message = [
                'pid' => $responseContent->pid,
                'transactionStatus' => $responseContent->transactionStatus
            ];

            if ($responseContent->transactionIDExternal) {
                $message['transactionIDExternal'] = $responseContent->transactionIDExternal;
            }

            return ApiResponse::respond([
                'description' => $responseContent->message,
                "message" => $message
            ]);
        } else {
            if (isset($responseContent->description) && isset($responseContent->message) && isset($responseContent->statusCode)) {
                return ApiResponse::respondWithError(
                    $responseContent->description,
                    $responseContent->message ,
                    $responseContent->statusCode
                );
            } else {
                return ApiResponse::respondWithError();
            }
        }
    }

    private function requestAdditional(array $request)
    {
        !isset($request['transferMade']) ? $request['transferMade'] = [] : null;

        $request['transferMade']['username'] = \Auth::user()->getName();
        $request['transferMade']['agent'] = \Auth::user()->getClientNameWICSystem();

        return $request;
    }

    // todo MUST replace into some facade helper or service

    /**
     * @param $responseContent
     * @param $requestAdded
     * @param $ip
     */
    private function proceedNewTransaction($responseContent, $requestAdded, $ip)
    {
        $status = TransactionStatuses::PENDING;
        if (isset($requestAdded['isWebsite']) && $requestAdded['isWebsite']) {
            $status = TransactionStatuses::CHECK_DOCUMENTS;
        }

        $transferDetails = [
            'transactionId' => array(
                'id' => $responseContent->pid,
                'status' => $status
            ),
            'currency' => array(
                'sending' => $requestAdded['currency']['source'],
                'receiving' => $responseContent->currencyTarget
            ),
            'amount' => $requestAdded['amount'],
            'rate' => $responseContent->rate,
            'receiptNo' => $requestAdded['receiptNO'],
            'transferType' => $responseContent->moneyTransferType,
            'receiving_country' => $requestAdded['receivingCountry'],
            'sender_country' => $requestAdded['sender']['resident']
        ];

        $receiverDetails = [
            'name' => array(
                'first' => $requestAdded['receiver']['name']['first'],
                'middle' => !empty($requestAdded['receiver']['name']['middle']) ? $requestAdded['receiver']['name']['middle'] : null,
                'last' => $requestAdded['receiver']['name']['last']
            ),
            'identifier' => array(
                'type' => strtolower($requestAdded['receiver']['identifierType']),
                'number' => $requestAdded['receiver']['identifier'],
            ),
            'city' => $requestAdded['receiver']['city'],
            'address' => $requestAdded['receiver']['address'],
            'phone' => $requestAdded['receiver']['phoneNumber'],
            'wicSystemId' => $responseContent->moneyReceiverID
        ];

        $senderDetails = [
            'name' => array(
                'first' => $requestAdded['sender']['name']['first'],
                'middle' =>  !empty($requestAdded['sender']['name']['middle']) ? $requestAdded['sender']['name']['middle'] : null,
                'last' => $requestAdded['sender']['name']['last']
            ),
            'identifier' => array(
                'type' => strtolower($requestAdded['sender']['identifierType']),
                'number' => $requestAdded['sender']['identifier'],
            ),
            'city' => $requestAdded['sender']['city'],
            'address' => $requestAdded['sender']['address'],
            'phone' => $requestAdded['sender']['phoneNumber'],
            'wicSystemId' => $responseContent->moneySenderID
        ];

        $this->transfersService->insertNewTransaction($transferDetails, $receiverDetails, $senderDetails, UsingServiceTypes::SELF_SERVICE);
    }
}
