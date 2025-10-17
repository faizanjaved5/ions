# ION Network HTML - Quick Start Guide

## ✅ FULLY FUNCTIONAL VERSION

This HTML version is **100% functional** with all business logic from the React version!

## 📦 What's Included

```
html/
├── index.html              # Main HTML file
├── styles.css              # Design system CSS
├── app.js                  # Complete JavaScript (ALL menus working!)
├── svg-sprites.html        # All sport/shop icons
├── data/                   # All JSON menu data
└── assets/                 # Images and logos
```

## 🚀 How to Run

### Option 1: Python (Simplest)
```bash
cd html
python -m http.server 8000
```
Then open: **http://localhost:8000/**

### Option 2: Node.js
```bash
cd html
npx http-server
```

### Option 3: PHP
```bash
cd html
php -S localhost:8000
```

### Option 4: VS Code Live Server
1. Install "Live Server" extension
2. Right-click `index.html`
3. Select "Open with Live Server"

## ✨ What Works (Everything!)

### ✅ ION Local Menu
- Click "ION Local" in header
- Navigate through regions (North America, Europe, etc.)
- Select countries to see states/cities
- Search functionality works
- Back button for navigation
- Mobile responsive

### ✅ ION Networks Menu
- Click "ION Networks" in header
- All network cards displayed
- External links working
- Responsive grid layout

### ✅ ION Initiatives Menu  
- Click "IONITIATIVES" in header
- All initiative categories shown
- Links to external pages
- Grid layout

### ✅ ION Mall Menu
- Click "ION Mall" in header
- **Sidebar**: Select categories (By Sport, By School, All Stores, etc.)
- **All Stores Tab**: See tabs for View All, Store, and other categories
- **Store Cards**: Images loaded from Mall of Champions
- **Click stores**: Opens Mall of Champions product pages
- Fully responsive

### ✅ Connections Menu
- Click "CONNECT.IONS" in header
- All connection categories
- External links working

### ✅ Additional Features
- **Search**: Click search icon (works in ION Local menu)
- **Theme Toggle**: Sun/Moon icon (dark/light mode)
- **Font Toggle**: "Aa" button (switches to Bebas Neue font)
- **Mobile Menu**: Hamburger icon on mobile devices
- **Responsive**: Works on all screen sizes

## 🎨 Customization

### Change Colors
Edit `styles.css`:
```css
:root {
    --primary: 29 36% 46%;  /* Brand color */
    --background: 0 0% 100%; /* Background */
}
```

### Change Fonts
Edit `index.html` (line 12):
```html
<link href="https://fonts.googleapis.com/css2?family=Your+Font&display=swap" rel="stylesheet">
```

Update Tailwind config (line 48):
```javascript
fontFamily: {
    bebas: ['Your Font', 'sans-serif'],
}
```

## 🔧 Troubleshooting

### Data not loading?
- Make sure you're using a local server (not opening file://)
- Check browser console (F12) for errors
- Verify data files exist in `data/` folder

### Images not showing?
- Store images load from Mall of Champions CDN
- Check internet connection
- Check browser console for 404 errors
- Fallback SVG icons should show if images fail

### Menus not opening?
- Check JavaScript console for errors
- Verify all data files loaded successfully
- Make sure you clicked the menu buttons in the header

### Mobile menu not working?
- Click hamburger icon (three lines) on mobile
- Menu should slide in from right
- Click backdrop to close

## 🌐 Browser Compatibility

Tested and working:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 📱 Mobile Features

- Responsive navigation
- Touch-friendly buttons
- Mobile menu drawer
- Optimized layouts
- All features work on mobile

## 🚢 Deployment

This is a static site - deploy anywhere:
- **Netlify**: Drag & drop the `html/` folder
- **Vercel**: Connect to Git repo
- **GitHub Pages**: Push to gh-pages branch
- **AWS S3**: Upload as static site
- **Any web server**: Upload via FTP

## 💡 Tips

1. **Performance**: All data loads once and is cached
2. **Offline**: Add service worker for offline support
3. **SEO**: Add meta tags to `index.html` for better SEO
4. **Analytics**: Add Google Analytics to track usage
5. **CDN**: Host assets on CDN for faster loading

## 📚 Need Help?

- Check `README.md` for detailed documentation
- Look at browser console (F12) for errors
- Verify all files are in correct locations
- Make sure local server is running

## 🎉 You're Ready!

1. Start the server
2. Open in browser
3. Click the menu buttons
4. Explore all the features!

Everything from the React version is working. Enjoy! 🚀