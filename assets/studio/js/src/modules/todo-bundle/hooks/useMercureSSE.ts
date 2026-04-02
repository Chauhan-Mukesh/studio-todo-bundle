/**
 * Custom hook for Mercure SSE subscription
 *
 * Subscribes to live todo updates via Server-Sent Events
 */

import { useEffect, useCallback } from 'react';

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
 * Subscribe to a Mercure hub topic for real-time updates
 */
export function useMercureSSE({ hubUrl, topic, onMessage, onError }: MercureOptions): void {
  const stableOnMessage = useCallback(onMessage, []);
  const stableOnError = useCallback(onError ?? (() => {}), []);

  useEffect(() => {
    const url = new URL(hubUrl);
    url.searchParams.append('topic', topic);

    const eventSource = new EventSource(url.toString(), { withCredentials: true });

    const handleMessage = (event: MessageEvent) => {
      try {
        const data = JSON.parse(event.data) as MercureEvent;
        stableOnMessage(data);
      } catch {
        // Ignore unparseable messages
      }
    };

    eventSource.addEventListener('message', handleMessage);
    eventSource.addEventListener('error', stableOnError);

    return () => {
      eventSource.removeEventListener('message', handleMessage);
      eventSource.removeEventListener('error', stableOnError);
      eventSource.close();
    };
  }, [hubUrl, topic, stableOnMessage, stableOnError]);
}
