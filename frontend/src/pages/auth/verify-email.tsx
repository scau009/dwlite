import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { authApi } from '@/lib/auth-api';
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';

type VerifyStatus = 'loading' | 'success' | 'error';

export function VerifyEmailPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';

  const [status, setStatus] = useState<VerifyStatus>('loading');
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!token) {
      setStatus('error');
      setMessage('Invalid verification link. No token provided.');
      return;
    }

    const verifyEmail = async () => {
      try {
        const result = await authApi.verifyEmail(token);
        setStatus('success');
        setMessage(result.message);
      } catch (error) {
        setStatus('error');
        const apiErr = error as { error?: string };
        setMessage(
          apiErr.error || 'Email verification failed. The link may have expired.'
        );
      }
    };

    verifyEmail();
  }, [token]);

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          {status === 'loading' && 'Verifying your email...'}
          {status === 'success' && 'Email verified!'}
          {status === 'error' && 'Verification failed'}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {status === 'loading' && (
          <div className="flex justify-center">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
          </div>
        )}
        {status === 'success' && (
          <Alert>
            <AlertDescription>{message}</AlertDescription>
          </Alert>
        )}
        {status === 'error' && (
          <Alert variant="destructive">
            <AlertDescription>{message}</AlertDescription>
          </Alert>
        )}
      </CardContent>
      {status !== 'loading' && (
        <CardFooter>
          <Link to="/login" className="text-primary hover:underline text-sm">
            Go to login
          </Link>
        </CardFooter>
      )}
    </Card>
  );
}
