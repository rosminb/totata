module.exports = {
  mode: 'production',

  entry: './es/esnext_features.js',

  optimization: {
    minimize: false
  },

  output: {
    path: __dirname + '/src',
    filename: 'esnext_features.bundle.js'
  }
};
