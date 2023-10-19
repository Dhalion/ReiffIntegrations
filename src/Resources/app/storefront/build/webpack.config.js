const { join, resolve } = require('path');

module.exports = () => {
    return {
        resolve: {
            alias: {
                'jquery': resolve(
                    join(__dirname, '..', 'node_modules', 'jquery'),
                ),
                'datatables.net': resolve(
                    join(__dirname, '..', 'node_modules', 'datatables.net'),
                ),
            },
        },
    };
};
