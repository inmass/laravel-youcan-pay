<?php

namespace Devinweb\LaravelYoucanPay;

use Devinweb\LaravelYoucanPay\Actions\CreateToken;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use YouCan\Pay\YouCanPay;

class LaravelYoucanPay
{
    /**
     * const REQUIRED_FIELD, defines the fields required during tokenization
     */
    const REQUIRED_FIELDS =[
        'order_id' => 'required',
        'amount' => 'required',
    ];

    /**
     * Youcan Pay instance
     *
     * @var \YouCan\Pay\YouCanPay
     */
    private $youcanpay_instance;

    /**
     * The config data
     *
     * @var array
     */
    private $config;

    /**
     * Tokenization fileds
     *
     * @var array
     */
    private $required_tokenization_fields;
    
    /**
     * Ip address
     *
     * @var string
     */
    private $ip;

    /**
     * @var \YouCan\Pay\API\Endpoints\TokenEndpoint
     */
    private $token;

    /**
     * The metadata is the data retrieved after the response or in the webhook
     *
     * @var array
     */
    private $metadata;
    
    /**
     * The Customer info
     *
     * @var array
     */
    private $customer_info;

    /**
     * Create a new LaravelYouCanPay instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = config('youcanpay');

        if ($this->config['sandboxMode']) {
            YouCanPay::setIsSandboxMode(true);
        }

        $this->required_tokenization_fields = self::REQUIRED_FIELDS;
        
        $this->youcanpay_instance = YouCanPay::instance()->useKeys($this->config['private_key'], $this->config['public_key']);
    }
    
    /**
     * Create a Tokenization
     *
     * @param array $paramters
     * @param \Illuminate\Http\Request $request
     * @return void
     */
    public function createTokenization(array $attributes, Request $request)
    {
        $this->validateTokenizationParameters($attributes);
        
        $this->ip = $request->ip();
        
        $metadata = $this->metadata??[];

        $customer_info = $this->customer_info??[];

        $this->token  =app(CreateToken::class)(
            $this->youcanpay_instance,
            [
                'attributes' => $attributes,
                'config' => $this->config,
                'customer_info' => $customer_info,
                'metadata' => $metadata,
                'ip'=> $this->ip
            ]
        );
        
        return $this;
    }


    /**
     * Set the customer data
     *
     * @param array $customer_info
     * @return $this
     */
    public function setCustomerInfo(array $customer_info)
    {
        $this->customer_info = $customer_info;

        return $this;
    }

    /**
     * Set the metadata data to use them when the app receive a callback
     *
     * @param array $metadata
     * @return $this
     */
    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Get the token id
     *
     * @return string
     */
    public function getId()
    {
        return $this->token->getId();
    }

    /**
     * Get the payment URL
     *
     * @param string|null $lang
     * @return string
     */
    public function getPaymentURL(?string $lang=null)
    {
        $lang = $lang ?? config('app.locale');
        return $this->token->getPaymentURL($lang);
    }


    /**
     * Check the keys (private_key, public_key)
     *
     * @param string|null $privateKey
     * @param string|null $publicKey
     * @return boolean
     */
    public function checkKeys(?string $privateKey = null, ?string $publicKey = null): bool
    {
        return YouCanPay::instance()->checkKeys($privateKey, $publicKey);
    }


    /**
     * Verify the webhook signature
     *
     * @param string $signature
     * @param array $payload
     * @return boolean
     */
    public function verifyWebhookSignature(string $signature, array $payload): bool
    {
        return $this->youcanpay_instance->verifyWebhookSignature($signature, $payload);
    }
    
    /**
     * Validate the webhook signature
     *
     * @param string $signature
     * @param array $payload
     * @throws \YouCan\Pay\API\Exceptions\InvalidWebhookSignatureException
     * @return void
     */
    public function validateWebhookSignature(string $signature, array $payload): void
    {
        $this->youcanpay_instance->validateWebhookSignature($signature, $payload);
    }

    /**
     * Validate the toknization required fields
     *
     * @param array $attributes
     * @throws \InvalidArgumentException
     * @return void
     */
    private function validateTokenizationParameters(array $attributes)
    {
        foreach ($this->required_tokenization_fields as $key => $value) {
            if ($value == 'required' && !Arr::has($attributes, $key)) {
                throw new InvalidArgumentException("The ${key} must be availabe in the array");
            }
        }
    }
}
