import { useState, type FormEvent } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router';
import { authApi } from '@/lib/auth-api';
import { validateResetPasswordForm } from '@/lib/validation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { ApiError } from '@/types/auth';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token') || '';

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [apiError, setApiError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  if (!token) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Invalid reset link</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-gray-600 dark:text-gray-400">
            This password reset link is invalid or has expired.
          </p>
        </CardContent>
        <CardFooter>
          <Link
            to="/forgot-password"
            className="text-primary hover:underline text-sm"
          >
            Request a new reset link
          </Link>
        </CardFooter>
      </Card>
    );
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setApiError(null);

    const validation = validateResetPasswordForm(password, confirmPassword);
    if (!validation.isValid) {
      setErrors(validation.errors);
      return;
    }
    setErrors({});

    setIsSubmitting(true);
    try {
      await authApi.resetPassword({ token, password });
      navigate('/login', {
        state: {
          message:
            'Password reset successfully. Please log in with your new password.',
        },
      });
    } catch (error) {
      const apiErr = error as ApiError;
      if (apiErr.violations) {
        setErrors(apiErr.violations);
      } else {
        setApiError(
          apiErr.error || 'Password reset failed. The link may have expired.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Reset your password</CardTitle>
        <CardDescription>Enter your new password below</CardDescription>
      </CardHeader>
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-4">
          {apiError && (
            <Alert variant="destructive">
              <AlertDescription>{apiError}</AlertDescription>
            </Alert>
          )}
          <div className="space-y-2">
            <Label htmlFor="password">New Password</Label>
            <Input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              disabled={isSubmitting}
            />
            {errors.password && (
              <p className="text-sm text-red-500">{errors.password}</p>
            )}
            <p className="text-xs text-gray-500">
              Min 8 characters with uppercase, lowercase, and number
            </p>
          </div>
          <div className="space-y-2">
            <Label htmlFor="confirmPassword">Confirm New Password</Label>
            <Input
              id="confirmPassword"
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              disabled={isSubmitting}
            />
            {errors.confirmPassword && (
              <p className="text-sm text-red-500">{errors.confirmPassword}</p>
            )}
          </div>
        </CardContent>
        <CardFooter>
          <Button type="submit" className="w-full" disabled={isSubmitting}>
            {isSubmitting ? 'Resetting...' : 'Reset password'}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
}
