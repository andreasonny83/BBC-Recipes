var gulp            = require('gulp'),
    browserSync     = require('browser-sync'),
    reload          = browserSync.reload,
    $               = require('gulp-load-plugins')(),
    del             = require('del'),
    runSequence     = require('run-sequence'),
    ftp             = require('vinyl-ftp'),
    minimist        = require('minimist'),
    gutil           = require('gulp-util');

var args       = minimist(process.argv.slice(2)),
    remoteHost = 'sonnywebdesign.com',
    remotePath = '/public_html/bbc_recipes/',


// optimize images
gulp.task('images', function() {
  return gulp.src('./images/**/*')
    .pipe($.changed('./_build/images'))
    .pipe($.imagemin({
      optimizationLevel: 3,
      progressive: true,
      interlaced: true
    }))
    .pipe(gulp.dest('./_build/images'));
});

// browser-sync task, only cares about compiled CSS
gulp.task('browser-sync', function() {
  browserSync({
    server: {
      baseDir: "./"
    }
  });
});

// minify JS
gulp.task('minify-js', function() {
  gulp.src('js/*.js')
    .pipe($.uglify())
    .pipe(gulp.dest('./_build/'));
});

// minify CSS
gulp.task('minify-css', function() {
  gulp.src(['./styles/**/*.css', '!./styles/**/*.min.css'])
    .pipe($.rename({suffix: '.min'}))
    .pipe($.minifyCss({keepBreaks:true}))
    .pipe(gulp.dest('./styles/'))
    .pipe(gulp.dest('./_build/css/'));
});

// minify HTML
gulp.task('minify-html', function() {
  var opts = {
    comments: true,
    spare: true,
    conditionals: true
  };

  gulp.src('./*.html')
    .pipe($.minifyHtml(opts))
    .pipe(gulp.dest('./_build/'));
});

// copy fonts from a module outside of our project (like Bower)
gulp.task('fonts', function() {
  gulp.src([
    './fonts/**/*.{ttf,woff,eof,eot,svg}',
    './bower_components/font-awesome/fonts/**/*.{ttf,woff,woff2,eof,eot,svg}',
  ])
    .pipe($.changed('./_build/fonts'))
    .pipe(gulp.dest('./_build/fonts'));
});

// start webserver
gulp.task('server', function(done) {
  return browserSync({
    server: {
      baseDir: './'
    }
  }, done);
});

// start webserver from _build folder to check how it will look in production
gulp.task('server-build', function(done) {
  return browserSync({
    server: {
      baseDir: './_build/'
    }
  }, done);
});

// delete build folder
gulp.task('clean:build', function (cb) {
  del([
    './_build/'
    // if we don't want to clean any file we can use negate pattern
    //'!dist/mobile/deploy.json'
  ], cb);
});

// concat files
gulp.task('concat', function() {
  gulp.src('./js/*.js')
    .pipe($.concat('scripts.js'))
    .pipe(gulp.dest('./_build/'));
});

// SASS task, will run when any SCSS files change & BrowserSync
// will auto-update browsers
gulp.task('sass', function() {
  return gulp.src('styles/style.scss')
    .pipe($.sourcemaps.init())
    .pipe($.sass({
      style: 'expanded'
    }))
    .on('error', gutil.log)
    .pipe($.sourcemaps.write())
    .pipe(gulp.dest('styles'))
    .pipe(reload({
      stream: true
    }));
});

// SASS Build task
gulp.task('sass:build', function() {
  return gulp.src('styles/style.scss')
    .pipe($.sass({
      style: 'compact'
    }))
    .on('error', gutil.log)
    .pipe($.autoprefixer('last 3 version'))
    .pipe($.uncss({
      html: ['./index.html', './views/**/*.html', './components/**/*.html'],
      ignore: [
        '.index',
        '.slick'
      ]
    }))
    .pipe($.minifyCss({
      keepBreaks: true,
      aggressiveMerging: false,
      advanced: false
    }))
    .pipe($.rename({suffix: '.min'}))
    .pipe(gulp.dest('_build/css'));
});

// BUGFIX: warning: possible EventEmitter memory leak detected. 11 listeners added.
require('events').EventEmitter.prototype._maxListeners = 100;

// index.html build
// script/css concatenation
gulp.task('usemin', function() {
  return gulp.src('./index.html')
    // add templates path
    .pipe($.htmlReplace({
      'templates': '<script type="text/javascript" src="js/templates.js"></script>'
    }))
    .pipe($.usemin({
      css: [$.minifyCss(), 'concat'],
      libs: [$.uglify()],
      angularlibs: [$.uglify()],
      appcomponents: [$.uglify()]
    }))
    .pipe(gulp.dest('./_build/'));
});

// make templateCache from all HTML files
gulp.task('templates', function() {
  return gulp.src([
      './**/*.html',
      '!bower_components/**/*.*',
      '!node_modules/**/*.*',
      '!_build/**/*.*'
    ])
    .pipe($.minifyHtml())
    .pipe($.angularTemplatecache({
      module: 'bbc_recipes'
    }))
    .pipe(gulp.dest('_build/js'));
});

// reload all Browsers
gulp.task('bs-reload', function() {
  browserSync.reload();
});

// default task to be run with `gulp` command
// this default task will run BrowserSync & then use Gulp to watch files.
// when a file is changed, an event is emitted to BrowserSync with the filepath.
gulp.task('default', ['browser-sync', 'sass', 'minify-css'], function() {
  gulp.watch('styles/*.css', function(file) {
    if (file.type === "changed") {
      reload(file.path);
    }
  });
  gulp.watch(['*.html', 'views/*.html'], ['bs-reload']);
  gulp.watch(['app/*.js', 'components/**/*.js', 'js/*.js'], ['bs-reload']);
  gulp.watch('styles/**/*.scss', ['sass', 'minify-css']);
});

/**
 * Gulp Test
 */
gulp.task('test', function() {
  console.log('nothing to test yet.');
});

/**
 * build task
 *
 */
gulp.task('build', function(callback) {
  runSequence(
    'clean:build',
    'sass:build',
    'images',
    'templates',
    'usemin',
    'fonts',
    callback);
});

// gulp deploy --user username --password password
gulp.task('deploy', function(callback) {
  runSequence(
    'clean:build',
    'sass:build',
    'images',
    'templates',
    'usemin',
    'fonts',
    'send',
    callback);
});

gulp.task('send', function( cb ) {
  var conn = ftp.create({
      host: remoteHost,
      user: args.user,
      password: args.password,
      parallel: 10,
      debug: true,
      log: gutil.log
    });

  var globs = [
      '_build/**/*'
    ];

    conn.rmdir( remotePath, function ( err ) {
      if ( err ) {
        // If the remote directory doesn't exisits, do nothing and continue with the upload
        // return cb( err );
      }
      gulp.src(globs, { base: './_build', buffer: false } )
        .pipe( conn.newer( remotePath ) )
        .pipe( conn.dest( remotePath ) );
    });
});
