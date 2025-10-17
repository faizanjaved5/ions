import React from 'react';

/**
 * Formats text by wrapping trademark symbols (™) in a styled span
 * to make them less prominent with smaller, lighter font
 */
export const formatTrademark = (text: string | React.ReactNode): React.ReactNode => {
  // If text is not a string (already processed by highlightION), return as-is
  if (typeof text !== 'string') {
    return text;
  }
  
  if (!text.includes('™')) {
    return text;
  }

  const parts = text.split('™');
  
  return parts.map((part, index) => (
    <React.Fragment key={index}>
      {part}
      {index < parts.length - 1 && (
        <sup className="text-[0.5em] font-light opacity-35 dark:opacity-30">
          ™
        </sup>
      )}
    </React.Fragment>
  ));
};
