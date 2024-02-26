var gulp         = require('gulp');
var sass         = require('gulp-sass');
var cleancss     = require('gulp-clean-css');
var autoprefixer = require('gulp-autoprefixer');
var del          = require('del');
//var imagemin     = require('gulp-imagemin');
//var using        = require('gulp-using');
//var minify       = require('gulp-minify');
var concat		 = require('gulp-concat');
var uglify       = require('gulp-uglify');

/*****************************************************************************/

var browserSync  = require('browser-sync').create();

function server_create(done) {
	browserSync.init('www', {
		open: 'external',
		host:  "fantager",
		proxy: "fantager",
		port: "8080"
	});
	return done();
}

function server_reload(done) {
	browserSync.reload();
	return done();
}

/*****************************************************************************/

function compiler_sass(done) {
	return gulp.src('app/scss/**/*.scss')
		.pipe(sass()) // Converts Sass to CSS with gulp-sass
		.pipe(cleancss())
		.pipe(gulp.dest('www/css'));
};

function compiler_autoprefixer(done) {
	return gulp.src('www/css/*.css')
		.pipe(autoprefixer({
			browsers: ['last 2 versions'],
			cascade: false
		}))
		.pipe(gulp.dest('www/css'));
}

function compiler_javascript(done) {
	gulp.src([
		'node_modules/svgxuse/svgxuse.js',
		'app/js/front.js'
	])
	.pipe(concat('all.min.js'))
	//  .pipe(uglify())
	.pipe(gulp.dest('www/js'));
	return done();
}

/*****************************************************************************/

function copy_staticFiles(done) {
	gulp.src('app/images/**/*')
		.pipe(gulp.dest('www/images'));
	return done();
}

function copy_images(done) {
	gulp.src('node_modules/bootstrap-icons/bootstrap-icons.svg')
 		.pipe(gulp.dest('www/images'));
	gulp.src('app/images/*.+(png|jpg|gif|svg)')
		.pipe(gulp.dest('www/images'));
	return done();
}

/*****************************************************************************/

function clean_dist(done) {
	del.sync('www/css');
	del.sync('www/images');
	del.sync('www/js');
	return done();
}

/*****************************************************************************/

gulp.task('watch', gulp.series(
	clean_dist,
	copy_staticFiles,
	copy_images,
	compiler_sass,
	compiler_autoprefixer,
	compiler_javascript,
	server_create,
	function(callback) {
		gulp.watch(
			'app/**/*.php',
			gulp.series(server_reload)
		);
		gulp.watch(
			'app/**/*.latte',
			gulp.series(server_reload)
		);
		gulp.watch(
			'app/scss/**/*.scss',
			gulp.series(compiler_sass, compiler_autoprefixer)
		);
		gulp.watch(
			'app/images/**/*.+(png|jpg|gif|svg)',
			gulp.series(copy_images, server_reload)
		);
		gulp.watch(
			[
				'app/js/**/*.js',
			],
			gulp.series(compiler_javascript)
		);
	}
));

/*****************************************************************************/
