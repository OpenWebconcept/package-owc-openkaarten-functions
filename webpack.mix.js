const mix = require( 'laravel-mix' ),
  fs = require( 'fs' ),
  path = require( 'path' );

const ALLOWED_FILES = ['.scss', '.js'];
const MIX_OPTIONS = {
  styles: {
    outputStyle: 'compressed'
  }
};

/**
 * Get all files from specific directory.
 * @param {string} directory path to file from root.
 * @return {string[]}
 */
const getFiles = function (directory) {
  return fs.readdirSync( directory ).filter( file => {
    return (ALLOWED_FILES.includes( path.extname( file ) ) || ALLOWED_FILES.includes( file )) ? fs.statSync( `${directory}/${file}` ).isFile() : false;
  } );
};

/**
 * Get all directories from a specific directory.
 * @param {string} directory The directory to check.
 * @return {string[]}
 */
const getDirectories = function (directory) {
  return fs.readdirSync( directory ).filter( function (file) {
    return fs.statSync( path.join( directory, file ) ).isDirectory();
  } );
};


/**
 * Checks if directory is empty.
 * @param {string} dirPath The path to the directory.
 * @return {boolean}
 */
const isDirectoryEmpty = function (dirPath) {
  try {
    const files = fs.readdirSync( dirPath );
    return files.length === 0;
  } catch (err) {
    return true; // Directory does not exist or there was an error accessing it
  }
}

/**
 * Checks if file is empty.
 * @param {string} filePath The path to the file.
 * @return {boolean}
 */
const isFileEmpty = function (filePath) {
  try {
    const stats = fs.statSync( filePath );
    return stats.size === 0;
  } catch (err) {
    return true; // File does not exist or there was an error accessing it
  }
}

/**
 * Loop through the community block directories and block directories and build any files necessary.
 *
 * @param {string} folder name of the folder to scan.
 * @param {string} outputFolder name of the folder to output.
 * @constructor
 */
const build_blocks = (folder, outputFolder = folder) => {
  Array.from( getDirectories( folder ) ).forEach( (blockDir) => {
    build_files( blockDir, `${folder}/${blockDir}`, `${outputFolder}/${blockDir}` );
  } );
};

/**
 * Little helper to reduce duplication.
 *
 * @param {string} typeDir The type of script directory.
 * @param {string} path The path of the input directory.
 * @param outputPath The path of the output directory.
 * @constructor
 */
const build_files = (typeDir, path, outputPath = path) => {
  const files = getFiles( path );

  if (0 === files.length) {
    return;
  }

  files.forEach( (file) => {
    switch (typeDir) {
      case 'styles':
        if (!isDirectoryEmpty( path ) && !isFileEmpty( `${path}/${file}` )) {
          mix.sass(
            `${path}/${file}`,
            outputPath
          ).options( MIX_OPTIONS.styles );
        }
        break;

      case 'scripts':
        if (!isDirectoryEmpty( path ) && !isFileEmpty( `${path}/${file}` )) {
          mix
            .js( `${path}/${file}`, outputPath )
            .vue( {version: 3, extractStyles: true} )
        }
        break;
    }
  } );
};

/**
 * Loop through the client directory and build any files necessary.
 *
 * @param {string} folder name of the folder to scan.
 * @param {string} outputFolder name of the folder to output.
 * @constructor
 */
const build_client = (folder, outputFolder = folder) => {
  Array.from( getDirectories( folder ) ).forEach( (typeDir) => {
    build_files( typeDir, `${folder}/${typeDir}`, outputFolder );

    const directories = Array.from( getDirectories( `${folder}/${typeDir}` ) );
    if (directories.length > 0) {
      directories.forEach( (dir) => build_files( typeDir, `${folder}/${typeDir}/${dir}`, `${outputFolder}/${dir}` ) );
    }
  } );
};

build_blocks( 'src', '' );

mix
  .setPublicPath( 'build' )
  .version()
  .sourceMaps();


