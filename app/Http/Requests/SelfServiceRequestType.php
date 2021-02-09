<?php
declare(strict_types=1);

namespace PullApi\Http\Requests\TransactionType\SelfService;

use PullApi\Decider\DeciderController;
use PullApi\Enums\CurrencyTypes;
use PullApi\Enums\PaymentTypes;
use PullApi\Http\Requests\ApiRequestType;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Specific self service Request class
 *
 * Class SelfServiceRequestType
 * @package PullApi\Http\Requests\TransactionType\SelfService
 */
abstract class SelfServiceRequestType extends ApiRequestType
{
    protected $signs = ["-", "/", "'"];

    /**
     * Called to overwrite some params, especially change letter cases and clear
     * 
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        $this->setJSONToUpper('receivingCountry');
        $this->setJSONToUpper('sendingCountry');
        $this->setJSONToUpper('currency.source');
        $this->setCurrencyTargetToUpper('currency.target');
        /*$this->setJSONToLower('transferType');*/ // not sure that it is correct to set it to lower, cause there can be problems with searching in the wfs system, but anyway it is correct
        $this->setJSONToLower('receiver.city');
        $this->setJSONClear('sender.name.first', $this->signs);
        $this->setJSONClear('sender.name.middle', $this->signs);
        $this->setJSONClear('sender.name.last', $this->signs);
        $this->setJSONToUpper('sender.resident');

        return parent::getValidatorInstance();
    }

    /**
     * As every request has General rules, this is getter for that
     *
     * @return array
     */
    public function getSelfServiceGeneralRules(): array
    {
        $receiptIds = $this->user()->transfers()->get()->pluck('receipt_id')->toArray();

        $rules = [
            "receiptNO" => ["required", "max:255", Rule::notIn($receiptIds)],

            "receivingCountry" => [
                'required',
                Rule::in($this->getAllowedCountries())
            ],
            "amount" =>
                [
                    'required',
                    'regex:/^((1?1?\.([1-9]\d*|0[1-9]\d*))|(([1-9]|0[1-9])\d*(\.\d+)?))$/',
                ],

            // todo identifier info block
            "sender.identifier" => "required|min:4|max:255",
            "sender.identifierExpire" => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after:' . date('Y-m-d'),
                'regex:/^([1-2]{1}[0-9]{3})-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/',
                'max:255'
            ],
            "sender.identifierType" => [
                'required',
                Rule::in($this->getIdentifyTypes())
            ],
            "sender.name.first" => "required|max:255",
            "sender.name.middle" => "max:255",
            "sender.name.last" => "required|max:255",
            "sender.address" => "required|max:255",
            "sender.city" => "required|max:255",
            "sender.phoneNumber" => "required|max:255",
            "sender.resident" => "required|max:255",
            "sender.birthDate" => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after:1900-01-01',
                'regex:/^([1-2]{1}[0-9]{3})-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/',
                'max:255'
            ],

            "receiver.identifier" => "required|max:255",
            "receiver.identifierType" => [
                'required',
                Rule::in($this->getIdentifyTypes())
            ],
            "receiver.name.first" => "required|max:255",
            "receiver.name.middle" => "max:255",
            "receiver.name.last" => "required|max:255",
            "receiver.address" => "required|max:255",
            "receiver.city" => "required|max:255",
            "receiver.resident" => "required|max:255",
            "receiver.phoneNumber" => "required|max:255",

            "currency.target" => [
                'required',
                Rule::in($this->getAllowedTargetCurrencies())
            ],
            "currency.source" => [
                'required',
                Rule::in($this->getAllowedSourceCurrencies())
            ],

            "transferMade.agent" => "max:255", // todo may be get from logged in one
        ];

        if ($this->isWebsite) {
            $rules["paymentType"] = [
                'required',
                Rule::in($this->getAllowedPaymentType())
            ];
            $rules["sender.bankWireInfo.bankOwnerName"] = [
                'required_if:paymentType,' . PaymentTypes::BANK_WIRE,
                'string'
            ];
            $rules["sender.bankWireInfo.accountNumber"] = [
                'required_if:paymentType,' . PaymentTypes::BANK_WIRE,
                'numeric'
            ];
            $rules["sender.bankWireInfo.bankName"] = [
                'required_if:paymentType,' . PaymentTypes::BANK_WIRE,
                'string'
            ];
            $rules["sender.bankWireInfo.branch"] = [
                'required_if:paymentType,' . PaymentTypes::BANK_WIRE,
                'string'
            ];
            $rules["sender.bankWireInfo.comment"] = [
                'string'
            ];
            $rules["nature"] = [
                'required',
                'json'
            ];
        }

        return $rules;
    }

    abstract protected function getTransferTypeGeneralRules();

    protected function getIdentifyTypes() //todo duplicated in  ChangeTransactionNameRequest
    {
        // todo exists in `documents_types` table
        return [
            'Driving licence', 'ID Card', 'Identity card', 'Passport'
        ];
    }

    /**
     * Rule getter for additional key
     *
     * @return array
     */
    public function getAdditionalRules(): array
    {
        return DeciderController::getAdditionalRules($this->json()->all());
    }

    /**
     * Combines
     * every request's general rules
     * pickup or deposit specific rules
     * additional rules
     *
     * @return array
     */
    public function getTotalRules(): array
    {
        return array_merge($this->getTransferTypeGeneralRules(), $this->getAdditionalRules());
    }

    /**
     * Allowed countries
     *
     * @return array
     */
    protected abstract function getAllowedCountries(): array;

    /**
     * Get Allowed Target Currencies
     *
     * @return array
     */
    private function getAllowedTargetCurrencies(): array
    {
        return [
            CurrencyTypes::CURR_USD,
            CurrencyTypes::CURR_EUR,
            CurrencyTypes::CURR_Local
        ];
    }

    /**
     * Get Allowed Source Currencies
     *
     * @return array
     */
    private function getAllowedSourceCurrencies(): array
    {
        return [
            CurrencyTypes::CURR_ILS,
            CurrencyTypes::CURR_USD,
            CurrencyTypes::CURR_HKD,
            CurrencyTypes::CURR_EUR
        ];
    }

    private function getAllowedPaymentType(): array
    {
        return [
            PaymentTypes::BANK_WIRE,
            PaymentTypes::CREDIT_CARD,
            PaymentTypes::W_PAY
        ];
    }

    /**
     * @param string $param
     * @return null|JsonResponse
     */
    private function setCurrencyTargetToUpper(string $param): ?JsonResponse
    {
        $jsonAll = $this->all();
        $valueByParam = data_get($jsonAll, $param);

        if ($valueByParam && $valueByParam !== CurrencyTypes::CURR_Local) {
            data_get($jsonAll, $param)
                ? data_set($jsonAll, $param, strtoupper(data_get($jsonAll, $param)))
                : null;
        }

        return $this->getInputSource()->replace($jsonAll);
    }


    public function getRateRules() {
        $rules = [
            "source" => [
                'required',
                Rule::in($this->getAllowedSourceCurrencies())
            ]
        ];

        if (!$this->sendingCurrency) {
            $rules = array_merge($rules, [
                "country" => 'required',
                "target" => [
                    'required',
                    Rule::in($this->getAllowedTargetCurrencies())
                ]
            ]);
        }
        return $rules;
    }
}
