import { Link } from "react-router-dom";
import * as LucideIcons from "lucide-react";
import { Avatar, AvatarImage, AvatarFallback } from "@/components/ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuth } from "@/contexts/AuthContext";

interface UserProfileProps {
  onLogout: () => void;
}

const UserProfile = ({ onLogout }: UserProfileProps) => {
  const { isLoggedIn, user } = useAuth();
  
  // Don't render if not logged in or no user data
  if (!isLoggedIn || !user) {
    return null;
  }

  // Get user initials for fallback
  const getInitials = (name: string) => {
    return name
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase();
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button className="relative cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 rounded-md">
          <Avatar className="h-9 w-9 rounded-md">
            <AvatarImage src={user.avatar} alt={user.name} />
            <AvatarFallback className="rounded-md bg-primary text-primary-foreground">
              {getInitials(user.name)}
            </AvatarFallback>
          </Avatar>
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="w-64 bg-[#1a1d21] border-gray-700" align="end" sideOffset={8}>
        <DropdownMenuLabel className="font-normal px-3 py-3">
          <div className="flex flex-col space-y-1">
            <p className="text-sm font-medium leading-none text-white">{user.name}</p>
            <p className="text-xs leading-none text-gray-400">
              {user.email}
            </p>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator className="bg-gray-700" />
        {user.menuItems.map((item, index) => {
          const isLogout = item.label === "Log Out";
          const IconComponent = LucideIcons[item.icon as keyof typeof LucideIcons] as React.ComponentType<{ className?: string }>;
          
          if (isLogout) {
            return (
              <DropdownMenuItem 
                key={index} 
                className="cursor-pointer text-gray-300 hover:text-white hover:bg-gray-800 focus:text-white focus:bg-gray-800 px-3 py-2.5"
                onClick={(e) => {
                  e.preventDefault();
                  onLogout();
                }}
              >
                {IconComponent && <IconComponent className="mr-3 h-4 w-4" />}
                <span>{item.label}</span>
              </DropdownMenuItem>
            );
          }
          
          return (
            <DropdownMenuItem key={index} asChild>
              <Link to={item.link} className="cursor-pointer text-gray-300 hover:text-white hover:bg-gray-800 focus:text-white focus:bg-gray-800 flex items-center px-3 py-2.5">
                {IconComponent && <IconComponent className="mr-3 h-4 w-4" />}
                <span>{item.label}</span>
              </Link>
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export default UserProfile;
