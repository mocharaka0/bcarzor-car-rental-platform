# 🚗 Car Rental Platform - Error Handling Guide

## 📋 Overview
This guide outlines the comprehensive error handling strategy for the Car Rental Platform, covering backend API errors, frontend user experience, mobile app resilience, and monitoring systems.

---

## 🎯 **Error Handling Philosophy**

### Core Principles
1. **User-Friendly**: Never show technical errors to end users
2. **Transparent**: Provide clear, actionable error messages
3. **Resilient**: Graceful degradation when services fail
4. **Traceable**: Every error has a unique identifier for tracking
5. **Recoverable**: Automatic retries and fallback mechanisms
6. **Monitored**: Real-time error tracking and alerting

---

## 🔧 **Backend Error Handling (Laravel)**

### ✅ **Custom Exception Classes**

```php
// app/Exceptions/Custom/PaymentException.php
class PaymentException extends Exception
{
    protected $errorCode;
    protected $userMessage;
    protected $context;
    
    public function __construct($message, $errorCode, $userMessage = null, $context = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->userMessage = $userMessage ?? 'A payment error occurred. Please try again.';
        $this->context = $context;
    }
}

// app/Exceptions/Custom/BusinessLogicException.php
class BusinessLogicException extends Exception
{
    // Vehicle not available, booking conflicts, etc.
}

// app/Exceptions/Custom/ExternalServiceException.php
class ExternalServiceException extends Exception
{
    // Google Maps, OpenAI, Firebase service errors
}

// app/Exceptions/Custom/DatabaseException.php
class DatabaseException extends Exception
{
    // Database connection, query errors
}
```

### ✅ **Global Exception Handler**

```php
// app/Exceptions/Handler.php
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        $requestId = Str::random(16);
        
        // Log the error with context
        Log::error('Application Error', [
            'request_id' => $requestId,
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'user_id' => $request->user()?->id,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        if ($request->expectsJson()) {
            return $this->renderApiError($exception, $requestId);
        }
        
        return parent::render($request, $exception);
    }
    
    private function renderApiError(Throwable $exception, string $requestId)
    {
        $status = 500;
        $errorCode = 'INTERNAL_SERVER_ERROR';
        $message = 'An unexpected error occurred. Please try again later.';
        
        // Handle specific exception types
        if ($exception instanceof PaymentException) {
            $status = 402;
            $errorCode = $exception->getErrorCode();
            $message = $exception->getUserMessage();
        } elseif ($exception instanceof BusinessLogicException) {
            $status = 422;
            $errorCode = 'BUSINESS_LOGIC_ERROR';
            $message = $exception->getMessage();
        } elseif ($exception instanceof ValidationException) {
            $status = 422;
            $errorCode = 'VALIDATION_ERROR';
            $message = 'Please check your input and try again.';
        } elseif ($exception instanceof AuthenticationException) {
            $status = 401;
            $errorCode = 'AUTHENTICATION_REQUIRED';
            $message = 'Please log in to continue.';
        } elseif ($exception instanceof AuthorizationException) {
            $status = 403;
            $errorCode = 'INSUFFICIENT_PERMISSIONS';
            $message = 'You do not have permission to perform this action.';
        }
        
        return response()->json([
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
            'request_id' => $requestId,
            'timestamp' => now()->toISOString(),
        ], $status);
    }
}
```

### ✅ **Structured Error Codes**

```php
// app/Enums/ErrorCode.php
enum ErrorCode: string
{
    // Authentication & Authorization
    case INVALID_CREDENTIALS = 'INVALID_CREDENTIALS';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';
    
    // Vehicle & Booking
    case VEHICLE_NOT_FOUND = 'VEHICLE_NOT_FOUND';
    case VEHICLE_NOT_AVAILABLE = 'VEHICLE_NOT_AVAILABLE';
    case BOOKING_CONFLICT = 'BOOKING_CONFLICT';
    case INVALID_BOOKING_DATES = 'INVALID_BOOKING_DATES';
    
    // Payment
    case PAYMENT_FAILED = 'PAYMENT_FAILED';
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case PAYMENT_METHOD_INVALID = 'PAYMENT_METHOD_INVALID';
    case COMMISSION_CALCULATION_ERROR = 'COMMISSION_CALCULATION_ERROR';
    
    // External Services
    case MAPS_API_ERROR = 'MAPS_API_ERROR';
    case OPENAI_API_ERROR = 'OPENAI_API_ERROR';
    case FIREBASE_ERROR = 'FIREBASE_ERROR';
    
    // System
    case DATABASE_ERROR = 'DATABASE_ERROR';
    case EXTERNAL_SERVICE_UNAVAILABLE = 'EXTERNAL_SERVICE_UNAVAILABLE';
    case RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
}
```

### ✅ **Payment Error Handling**

```php
// app/Services/PaymentService.php
class PaymentService
{
    public function processPayment($amount, $paymentMethod, $bookingId)
    {
        try {
            // Try Stripe first
            return $this->processStripePayment($amount, $paymentMethod, $bookingId);
        } catch (PaymentException $e) {
            Log::warning('Stripe payment failed, trying PayPal', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage()
            ]);
            
            try {
                // Fallback to PayPal
                return $this->processPayPalPayment($amount, $paymentMethod, $bookingId);
            } catch (PaymentException $e) {
                Log::warning('PayPal payment failed, falling back to bank transfer', [
                    'booking_id' => $bookingId,
                    'error' => $e->getMessage()
                ]);
                
                // Final fallback to bank transfer
                return $this->initiateBankTransfer($amount, $bookingId);
            }
        }
    }
    
    private function processStripePayment($amount, $paymentMethod, $bookingId)
    {
        try {
            $stripe = new StripeClient(config('stripe.secret_key'));
            
            $paymentIntent = $stripe->paymentIntents->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'payment_method' => $paymentMethod,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => ['booking_id' => $bookingId]
            ]);
            
            if ($paymentIntent->status === 'succeeded') {
                return $this->createPaymentRecord($paymentIntent, $bookingId, 'stripe');
            }
            
            throw new PaymentException(
                'Stripe payment failed',
                ErrorCode::PAYMENT_FAILED,
                'Your payment could not be processed. Please try a different payment method.'
            );
            
        } catch (StripeException $e) {
            throw new PaymentException(
                'Stripe API error: ' . $e->getMessage(),
                ErrorCode::PAYMENT_FAILED,
                'Payment processing is temporarily unavailable. Please try again later.'
            );
        }
    }
}
```

### ✅ **Logging Strategy**

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'payments', 'security', 'performance'],
    ],
    
    'payments' => [
        'driver' => 'single',
        'path' => storage_path('logs/payments.log'),
        'level' => 'info',
    ],
    
    'security' => [
        'driver' => 'single',
        'path' => storage_path('logs/security.log'),
        'level' => 'warning',
    ],
    
    'performance' => [
        'driver' => 'single',
        'path' => storage_path('logs/performance.log'),
        'level' => 'debug',
    ],
    
    'gps' => [
        'driver' => 'single',
        'path' => storage_path('logs/gps.log'),
        'level' => 'info',
    ],
],
```

---

## 🖥️ **Frontend Error Handling (Next.js)**

### ✅ **Error Boundary Component**

```typescript
// components/ErrorBoundary.tsx
interface ErrorBoundaryState {
  hasError: boolean;
  errorId?: string;
  errorMessage?: string;
}

class ErrorBoundary extends Component<
  { children: ReactNode; fallback?: ReactNode },
  ErrorBoundaryState
> {
  constructor(props: any) {
    super(props);
    this.state = { hasError: false };
  }
  
  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    const errorId = Math.random().toString(36).substr(2, 9);
    
    // Log error to monitoring service
    console.error('Error Boundary caught error:', {
      errorId,
      message: error.message,
      stack: error.stack,
    });
    
    return {
      hasError: true,
      errorId,
      errorMessage: 'Something went wrong. Please refresh the page or try again later.',
    };
  }
  
  render() {
    if (this.state.hasError) {
      return this.props.fallback || (
        <div className="error-fallback">
          <h2>Oops! Something went wrong</h2>
          <p>{this.state.errorMessage}</p>
          <p className="error-id">Error ID: {this.state.errorId}</p>
          <button onClick={() => window.location.reload()}>
            Refresh Page
          </button>
        </div>
      );
    }
    
    return this.props.children;
  }
}
```

### ✅ **API Error Handler**

```typescript
// lib/api-client.ts
interface ApiError {
  success: false;
  error_code: string;
  message: string;
  request_id: string;
  timestamp: string;
}

class ApiClient {
  private async handleResponse<T>(response: Response): Promise<T> {
    if (!response.ok) {
      const errorData: ApiError = await response.json();
      
      // Log error for monitoring
      console.error('API Error:', {
        status: response.status,
        errorCode: errorData.error_code,
        requestId: errorData.request_id,
        url: response.url,
      });
      
      // Show user-friendly error message
      this.showErrorToast(this.getUserFriendlyMessage(errorData.error_code));
      
      throw new Error(errorData.message);
    }
    
    return response.json();
  }
  
  private getUserFriendlyMessage(errorCode: string): string {
    const errorMessages: Record<string, string> = {
      'VEHICLE_NOT_AVAILABLE': 'This vehicle is no longer available for your selected dates.',
      'PAYMENT_FAILED': 'Payment could not be processed. Please try a different payment method.',
      'INSUFFICIENT_PERMISSIONS': 'You don\'t have permission to perform this action.',
      'RATE_LIMIT_EXCEEDED': 'Too many requests. Please wait a moment and try again.',
      'NETWORK_ERROR': 'Connection problem. Please check your internet and try again.',
    };
    
    return errorMessages[errorCode] || 'An unexpected error occurred. Please try again.';
  }
  
  private showErrorToast(message: string) {
    // Integration with your toast notification system
    toast.error(message);
  }
}
```

### ✅ **Form Error Handling**

```typescript
// components/BookingForm.tsx
export default function BookingForm() {
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const handleSubmit = async (data: BookingFormData) => {
    setIsSubmitting(true);
    setErrors({});
    
    try {
      await apiClient.createBooking(data);
      router.push('/booking-success');
    } catch (error) {
      if (error instanceof ValidationError) {
        setErrors(error.fieldErrors);
      } else if (error instanceof BusinessLogicError) {
        toast.error(error.message);
      } else {
        toast.error('Unable to create booking. Please try again.');
      }
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <form onSubmit={handleSubmit}>
      <Input
        name="pickup_date"
        error={errors.pickup_date}
        disabled={isSubmitting}
      />
      {/* Other form fields */}
      <Button type="submit" loading={isSubmitting}>
        Create Booking
      </Button>
    </form>
  );
}
```

---

## 📱 **Mobile App Error Handling (React Native)**

### ✅ **Global Error Handler**

```typescript
// utils/errorHandler.ts
import crashlytics from '@react-native-firebase/crashlytics';

class ErrorHandler {
  static setupGlobalErrorHandling() {
    // Handle JavaScript errors
    ErrorUtils.setGlobalHandler((error, isFatal) => {
      console.error('Global Error:', error);
      crashlytics().recordError(error);
      
      if (isFatal) {
        this.showFatalErrorDialog();
      }
    });
    
    // Handle promise rejections
    const originalHandler = require('react-native/Libraries/Promise').default.done;
    require('react-native/Libraries/Promise').default.done = function(onFulfilled, onRejected) {
      return originalHandler.call(this, onFulfilled, (error) => {
        console.error('Unhandled Promise Rejection:', error);
        crashlytics().recordError(error);
        if (onRejected) onRejected(error);
      });
    };
  }
  
  static showFatalErrorDialog() {
    Alert.alert(
      'App Error',
      'The app has encountered an unexpected error. Please restart the app.',
      [{ text: 'Restart', onPress: () => RNRestart.Restart() }]
    );
  }
}
```

### ✅ **Network Error Handling**

```typescript
// services/ApiService.ts
class ApiService {
  private async makeRequest<T>(url: string, options: RequestInit): Promise<T> {
    try {
      const response = await fetch(url, {
        ...options,
        timeout: 10000, // 10 second timeout
      });
      
      if (!response.ok) {
        throw new ApiError(response.status, await response.json());
      }
      
      return await response.json();
    } catch (error) {
      if (error instanceof TypeError && error.message.includes('Network request failed')) {
        throw new NetworkError('Please check your internet connection and try again.');
      }
      
      if (error.name === 'AbortError') {
        throw new TimeoutError('Request timed out. Please try again.');
      }
      
      throw error;
    }
  }
}
```

---

## 📊 **Monitoring & Alerting**

### ✅ **Error Tracking**

```php
// app/Services/ErrorTrackingService.php
class ErrorTrackingService
{
    public function trackError(Throwable $exception, array $context = [])
    {
        $errorData = [
            'id' => Str::uuid(),
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'context' => $context,
            'occurred_at' => now(),
        ];
        
        // Store in database for analysis
        DB::table('error_logs')->insert($errorData);
        
        // Send to external monitoring service (e.g., Sentry, Bugsnag)
        if (app()->environment('production')) {
            $this->sendToMonitoringService($errorData);
        }
        
        // Check if this error requires immediate attention
        if ($this->isCriticalError($exception)) {
            $this->sendCriticalErrorAlert($errorData);
        }
    }
    
    private function isCriticalError(Throwable $exception): bool
    {
        return $exception instanceof DatabaseException ||
               $exception instanceof PaymentException ||
               (method_exists($exception, 'getCode') && $exception->getCode() >= 500);
    }
    
    private function sendCriticalErrorAlert(array $errorData)
    {
        // Send to Slack, email, or SMS alerting system
        Notification::route('slack', config('slack.webhook_url'))
            ->notify(new CriticalErrorAlert($errorData));
    }
}
```

### ✅ **Health Check Endpoints**

```php
// routes/api.php
Route::get('/health', function () {
    $checks = [
        'database' => $this->checkDatabase(),
        'cache' => $this->checkCache(),
        'storage' => $this->checkStorage(),
        'external_apis' => $this->checkExternalApis(),
    ];
    
    $overall = collect($checks)->every(fn($check) => $check['status'] === 'ok');
    
    return response()->json([
        'status' => $overall ? 'ok' : 'error',
        'timestamp' => now()->toISOString(),
        'checks' => $checks,
    ], $overall ? 200 : 503);
});
```

---

## 🔄 **Recovery Strategies**

### ✅ **Automatic Retries**

```php
// app/Services/ResilientApiService.php
class ResilientApiService
{
    public function callExternalApi($endpoint, $data, $maxRetries = 3)
    {
        $attempt = 1;
        
        while ($attempt <= $maxRetries) {
            try {
                return $this->makeApiCall($endpoint, $data);
            } catch (ExternalServiceException $e) {
                Log::warning("API call failed (attempt {$attempt}/{$maxRetries})", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
                
                if ($attempt === $maxRetries) {
                    throw $e;
                }
                
                // Exponential backoff
                sleep(pow(2, $attempt - 1));
                $attempt++;
            }
        }
    }
}
```

### ✅ **Circuit Breaker Pattern**

```php
// app/Services/CircuitBreakerService.php
class CircuitBreakerService
{
    private $failureCount = 0;
    private $lastFailureTime = null;
    private $state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN
    
    public function execute(callable $operation)
    {
        if ($this->state === 'OPEN') {
            if ($this->shouldAttemptReset()) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new ExternalServiceException('Service temporarily unavailable');
            }
        }
        
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (Exception $e) {
            $this->onFailure();
            throw $e;
        }
    }
    
    private function onSuccess()
    {
        $this->failureCount = 0;
        $this->state = 'CLOSED';
    }
    
    private function onFailure()
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->failureCount >= 5) {
            $this->state = 'OPEN';
        }
    }
}
```

---

## 📚 **Error Documentation**

### ✅ **Error Code Reference**

| Error Code | Description | User Message | Recovery Action |
|------------|-------------|--------------|-----------------|
| `VEHICLE_NOT_AVAILABLE` | Vehicle booked for selected dates | "This vehicle is not available for your selected dates." | Show alternative vehicles |
| `PAYMENT_FAILED` | Payment processing error | "Payment could not be processed." | Try different payment method |
| `INSUFFICIENT_FUNDS` | Insufficient balance | "Insufficient funds in your account." | Add funds or use different method |
| `GPS_UNAVAILABLE` | GPS service not working | "Location services are unavailable." | Enable GPS or continue without tracking |
| `NETWORK_ERROR` | Internet connectivity issue | "Please check your connection." | Retry when connection restored |

---

## 🎯 **Best Practices**

### ✅ **Do's**
- Always provide user-friendly error messages
- Log errors with sufficient context for debugging
- Implement graceful degradation for non-critical features
- Use structured error codes for programmatic handling
- Monitor error rates and patterns
- Test error scenarios thoroughly

### ✅ **Don'ts**
- Never expose sensitive information in error messages
- Don't ignore errors or fail silently
- Avoid generic "Something went wrong" messages without context
- Don't retry indefinitely without backoff
- Never log sensitive data like passwords or payment details

---

**Last Updated:** [Date]  
**Version:** 1.0  
**Maintained By:** Development Team