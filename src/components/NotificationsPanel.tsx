import { useState, useEffect } from "react";
import { X, Check, CheckCheck } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { ScrollArea } from "@/components/ui/scroll-area";
import { useAuth } from "@/contexts/AuthContext";

interface Notification {
  id: number;
  message: string;
  time: string;
  read: boolean;
}

interface NotificationsPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const NotificationsPanel = ({ open, onOpenChange }: NotificationsPanelProps) => {
  const { user } = useAuth();
  const [notifications, setNotifications] = useState<Notification[]>(
    user?.notifications || []
  );

  // Update notifications when user changes
  useEffect(() => {
    if (user) {
      setNotifications(user.notifications || []);
    }
  }, [user]);

  const unreadCount = notifications.filter(n => !n.read).length;

  const markAsRead = (id: number) => {
    setNotifications(prev =>
      prev.map(notif =>
        notif.id === id ? { ...notif, read: true } : notif
      )
    );
  };

  const markAllAsRead = () => {
    setNotifications(prev =>
      prev.map(notif => ({ ...notif, read: true }))
    );
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:w-[400px] p-0 bg-[#1a1d21] border-gray-700">
        <SheetHeader className="px-6 py-4 border-b border-gray-700">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <SheetTitle className="text-white">
                Notifications
                {unreadCount > 0 && (
                  <span className="ml-2 text-xs font-normal text-gray-400">
                    ({unreadCount} unread)
                  </span>
                )}
              </SheetTitle>
            </div>
            {unreadCount > 0 && (
              <Button
                variant="ghost"
                size="sm"
                onClick={markAllAsRead}
                className="h-8 text-xs text-gray-400 hover:text-white hover:bg-gray-800"
              >
                <CheckCheck className="mr-1.5 h-3.5 w-3.5" />
                Mark all read
              </Button>
            )}
          </div>
        </SheetHeader>

        <ScrollArea className="h-[calc(100vh-5rem)]">
          <div className="p-4">
            {notifications.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 text-center">
                <div className="mb-3 rounded-full bg-gray-800 p-3">
                  <Check className="h-6 w-6 text-gray-500" />
                </div>
                <p className="text-sm text-gray-400">No notifications</p>
              </div>
            ) : (
              <div className="space-y-2">
                {notifications.map((notification) => (
                  <div
                    key={notification.id}
                    className={`relative rounded-lg border p-4 transition-all ${
                      !notification.read
                        ? 'border-primary/30 bg-gray-800/50 hover:bg-gray-800/70'
                        : 'border-gray-700 bg-gray-900/30 hover:bg-gray-900/50'
                    }`}
                  >
                    {!notification.read && (
                      <div className="absolute top-3 right-3">
                        <div className="h-2 w-2 rounded-full bg-primary animate-pulse" />
                      </div>
                    )}
                    
                    <div className="pr-6">
                      <p className="text-sm text-gray-200 leading-relaxed mb-2">
                        {notification.message}
                      </p>
                      <div className="flex items-center justify-between">
                        <p className="text-xs text-gray-500">{notification.time}</p>
                        {!notification.read && (
                          <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => markAsRead(notification.id)}
                            className="h-7 text-xs text-primary hover:text-primary hover:bg-primary/10"
                          >
                            <Check className="mr-1 h-3 w-3" />
                            Mark read
                          </Button>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
};

export default NotificationsPanel;
