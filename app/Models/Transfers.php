<?php
declare(strict_types=1);

namespace PullApi\Models;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


/**
 * Class Transfers
 * @package PullApi\Models
 */

class Transfers extends AbstractModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transaction_number','status','sender_coin','receiver_coin','amount','receipt_id','transfer_type_fk','rate','sender_country','receiving_country'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'transfer_type_fk', 'user_fk', 'service_type_fk', 'sender_client_fk','receiver_client_fk','rate', 'deleted_at'
    ];


    protected $table = 'transfers';

   /**
     * @return string
     */
    public function getTransactionNumber(): string
    {
        return $this->transaction_number;
    }

    /**
     * @param string $transaction_number
     */
    public function setTransactionNumber(string $transaction_number)
    {
        $this->transaction_number = $transaction_number;
    }

    /**
     * @return int
     */
    public function getSenderClientFk(): int
    {
        return $this->sender_client_fk;
    }

    /**
     * @param int $sender_client_fk
     */
    public function setSenderClientFk(int $sender_client_fk)
    {
        $this->sender_client_fk = $sender_client_fk;
    }

    /**
     * @return int
     */
    public function getReceiverClientFk(): int
    {
        return $this->receiver_client_fk;
    }

    /**
     * @param int $receiver_client_fk
     */
    public function setReceiverClientFk(int $receiver_client_fk)
    {
        $this->receiver_client_fk = $receiver_client_fk;
    }

    /**
     * @return int
     */
    public function getTransferTypeFk(): int
    {
        return $this->transfer_type_fk;
    }

    /**
     * @param int $transfer_type_fk
     */
    public function setTransferTypeFk(int $transfer_type_fk)
    {
        $this->transfer_type_fk = $transfer_type_fk;
    }

    /**
     * @return int
     */
    public function getServiceTypeFk(): int
    {
        return $this->service_type_fk;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status, $immediatelySave = false)
    {
        $this->status = $status;

        $immediatelySave ? $this->save() : null;

        return $this;
    }

    /**
     * @return string
     */
    public function getSenderCoin(): string
    {
        return $this->sender_coin;
    }

    /**
     * @param string $sender_coin
     */
    public function setSenderCoin(string $sender_coin)
    {
        $this->sender_coin = $sender_coin;
    }

    /**
     * @return string
     */
    public function getReceiverCoin(): string
    {
        return $this->receiver_coin;
    }

    /**
     * @param string $receiver_coin
     */
    public function setReceiverCoin(string $receiver_coin)
    {
        $this->receiver_coin = $receiver_coin;
    }

    /**
     * @return float
     */
    public function getRate(): float
    {
        return (float)$this->rate;
    }

    /**
     * @param float $rate
     */
    public function setRate(float $rate)
    {
        $this->rate = $rate;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return (float)$this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount)
    {
        $this->amount = $amount;
    }


    /**
     * @return string
     */
    public function getReceiptId()
    {
        return $this->receipt_id;
    }

    /**
     * @param string $receipt_id
     */
    public function setReceiptId(string $receipt_id)
    {
        $this->receipt_id = $receipt_id;
    }



    /**
      * Get the user record associated with the transaction.
     */
    public function sender() : BelongsTo
    {
        return $this->belongsTo(Clients::class,'sender_client_fk');
    }

    /**
     * Get the user record associated with the transaction.
     */
    public function receiver() : BelongsTo
    {
        return $this->belongsTo(Clients::class,'receiver_client_fk');
    }

    /**
     * Transfer Type via which transaction created
     *
     * @return BelongsTo
     */
    public function transferType() : BelongsTo
    {
        return $this->belongsTo(TransferType::class,'transfer_type_fk');
    }

    /**
     * Get the user/client who made transfer
     *
     * @return BelongsTo
     */
    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class,'user_fk');
    }

    /**
     * Get the user record associated with the transaction.
     */
    public function transferService() : BelongsTo
    {
        return $this->belongsTo(TransferServices::class,'service_type_fk');
    }
}
