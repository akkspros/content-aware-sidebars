'use strict';
const gulp = require('gulp');
const less = require('gulp-less');
const uglify = require('gulp-uglify');
const rename = require("gulp-rename");
const replace = require("gulp-replace");
const cleanCSS = require("gulp-clean-css");
const zip = require("gulp-zip");
const freemius = require("gulp-freemius-deploy");
const fs_config = require( './fs-config.json' );

gulp.task('less', function (done) {
	return gulp.src('assets/css/style.less')
		.pipe(less({
			plugins: [new (require('less-plugin-autoprefix'))({ browsers: ["> 1%"] })]
		}))
		.pipe(cleanCSS({
			compatibility: '*',
			level: {
				1: {
					specialComments: true
				},
				2: {}
			}
		}))
		.pipe(gulp.dest('assets/css'));
});

gulp.task('uglify', function () {
	return gulp.src(['js/*.js','!js/*.min.js'])
		.pipe(uglify({
			compress: {
				drop_console: true
			},
			mangle: {
				reserved: ['jQuery', 'CASAdmin','$']
			},
			output: {
				comments: 'some'
			},
			warnings: false
		}))
		.pipe(rename({extname: '.min.js'}))
		.pipe(gulp.dest('js'));
});

gulp.task('zip', function() {
	return gulp.src(['**','!fs-config.json','!**/*.less','!build{,/**}','!**/node_modules{,/**}', '!gulpfile.js', '!package.json'],{base:'../'})
		.pipe(zip('content-aware-sidebars.zip'))
		.pipe(gulp.dest('build'));
});

gulp.task('update-version', function (done) {
	const version = [...process.argv].pop();
	return gulp.src(['app.php', 'readme.txt', 'content-aware-sidebars.php'])
		.pipe(replace(/(PLUGIN\_VERSION = '|Version: |Stable tag: )[\.0-9]{3,5}/gs, '$1' + version))
		.pipe(gulp.dest('./'));
});

gulp.task('freemius', function() {
	return freemius( gulp, {
		developer_id   : fs_config.developer_id,
		plugin_id      : fs_config.plugin_id,
		public_key     : fs_config.public_key,
		secret_key     : fs_config.secret_key,
		zip_name       : 'content-aware-sidebars.zip',
		zip_path       : 'build/',
		add_contributor: true
	});
});

gulp.task('watch', function() {
	gulp.watch('assets/css/style.less', gulp.parallel('less'));
	gulp.watch(['js/*.js','!js/*.min.js'], gulp.parallel('uglify'));
});

gulp.task('build', gulp.parallel('less','uglify'));
gulp.task('deploy', gulp.series('build','zip'));
gulp.task('default', gulp.parallel('build'));
