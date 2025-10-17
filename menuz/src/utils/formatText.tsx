import React from 'react';

/**
 * Formats text by highlighting "ION" and styling trademark symbols (™)
 * to make them less prominent with smaller, lighter font
 */
export const formatTextWithHighlights = (text: string): React.ReactNode => {
  // First split by "ION" to handle highlighting
  const ionParts = text.split(/\b(ION)\b/);
  
  return ionParts.map((part, ionIndex) => {
    // If this part is "ION", highlight it
    if (part === 'ION') {
      return (
        <span key={`ion-${ionIndex}`} className="text-primary">
          ION
        </span>
      );
    }
    
    // For non-ION parts, check for trademark symbols
    if (!part.includes('™')) {
      return <React.Fragment key={`ion-${ionIndex}`}>{part}</React.Fragment>;
    }
    
    // Split by trademark and format it
    const tmParts = part.split('™');
    return (
      <React.Fragment key={`ion-${ionIndex}`}>
        {tmParts.map((tmPart, tmIndex) => (
          <React.Fragment key={`tm-${tmIndex}`}>
            {tmPart}
            {tmIndex < tmParts.length - 1 && (
              <sup className="text-[0.5em] font-light opacity-35 dark:opacity-30">
                ™
              </sup>
            )}
          </React.Fragment>
        ))}
      </React.Fragment>
    );
  });
};
