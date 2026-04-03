/**
 * Custom hook for Mercure SSE subscription
 *
 * Subscribes to live todo updates via Server-Sent Events
 */

import { useEffect, useRef } from 'react';

export type MercureEvent = {
  event: 'created' | 'updated' | 'deleted' | 'completed' | 'restored';
  todo: Record<string, unknown>;
  previous?: Record<string, unknown>;
};

type MercureOptions = {
  hubUrl: string;
  topic: string;
  onMessage: (event: MercureEvent) => void;
  onError?: (error: Event) => void;
};

/**
 * Subscribe to a Mercure hub topic for real-time updates.
 *
 * Uses refs to hold the latest callbacks so the EventSource is only
 * opened/closed when hubUrl or topic changes, not on every render.
 * Supports both absolute URLs and relative paths (e.g. /.well-known/mercure).
 */
export function useMercureSSE({ hubUrl, topic, onMessage, onError }: MercureOptions): void {
  // Keep callback refs up-to-date on every render so the effect never
  // captures a stale closure without needing to re-open the EventSource.
  const onMessageRef = useRef(onMessage);
  const onErrorRef = useRef(onError);

  useEffect(() => {
    onMessageRef.current = onMessage;
  });

  useEffect(() => {
    onErrorRef.current = onError;
  });

  useEffect(() => {
    let url: URL;
    try {
      // new URL() requires an absolute URL; use location.href as base so that
      // relative paths like '/.well-known/mercure' are resolved correctly.
      url = new URL(hubUrl, window.location.href);
    } catch {
      console.error('useMercureSSE: invalid hub URL', hubUrl);
      return;
    }
    url.searchParams.append('topic', topic);

    const eventSource = new EventSource(url.toString(), { withCredentials: true });

    const handleMessage = (event: MessageEvent) => {
      try {
        const data = JSON.parse(event.data) as MercureEvent;
        onMessageRef.current(data);
      } catch {
        // Ignore unparseable messages
      }
    };

    const handleError = (event: Event) => {
      onErrorRef.current?.(event);
    };

    eventSource.addEventListener('message', handleMessage);
    eventSource.addEventListener('error', handleError);

    return () => {
      eventSource.removeEventListener('message', handleMessage);
      eventSource.removeEventListener('error', handleError);
      eventSource.close();
    };
  }, [hubUrl, topic]);
}
