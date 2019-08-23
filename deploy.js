var { execSync, spawn } = require('child_process');

const deploySettings = require('./deploy.json'),
	package = deploySettings.config;

const bootstrap = async () => {
	let cd = new Date();

	console.info(deploySettings.files);

	let filename = `deploy/${package.name}_${cd.getDate()}-${cd.getMonth()+1}-${cd.getFullYear()}-${cd.getHours()}_${cd.getMinutes()}.zip`;

	execSync(`zip ${filename} -r ${deploySettings.files.join(' ')}`);

	execSync(`cp ${filename} deploy/${package.name}.${package.version}.zip`);
};

bootstrap();
